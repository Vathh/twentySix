<?php

namespace App\Services\League;

use App\Domain\GameScoring\GameLegScoreValidator;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\League\LeagueMatchdayCalendar;
use App\Domain\League\LeagueDivisionSnapshot;
use App\Domain\League\LeaguePlayoffPairing;
use App\Domain\League\LeaguePromotionResolver;
use App\Domain\League\LeagueStandingCalculator;
use App\Domain\League\LeagueStandingRow;
use App\Domain\League\LeagueTieBreakBracket;
use App\Domain\League\RoundRobinScheduler;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueMatchdayPlanning;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\LeagueWalkoverType;
use App\Enums\MatchWinMode;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;
use App\Repositories\League\LeagueRepository;
use App\Repositories\League\LeagueSeasonRepository;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeagueSeasonService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private LeagueSeasonRepository $leagueSeasonRepository,
    ) {
    }

    public function getForPolicy(int $seasonId): LeagueSeason
    {
        return $this->leagueSeasonRepository->findWithGraph($seasonId);
    }

    public function getGameForPolicy(int $gameId): LeagueGame
    {
        return $this->leagueSeasonRepository->findGame($gameId);
    }

    public function create(
        int $leagueId,
        string $name,
        string $calendarMode,
        int $roundsEach,
        string $startDate,
        ?string $endDate,
        ?string $deadlineAt,
        bool $startNow = false,
        ?int $matchdayLengthDays = null,
        ?string $matchdayPlanning = null,
        bool $allowsDraws = false,
        int $winLength = 2,
    ): LeagueSeason {
        if ($this->leagueRepository->hasOpenSeason($leagueId)) {
            throw ValidationException::withMessages([
                'name' => 'Ta liga ma już otwarty sezon (szkic lub w trakcie).',
            ]);
        }

        $mode = LeagueCalendarMode::from($calendarMode);
        if ($roundsEach !== 1 && $roundsEach !== 2) {
            throw ValidationException::withMessages(['rounds_each' => 'Każdy z każdym: 1 albo 2 spotkania.']);
        }

        $lengthDays = null;
        $planning = null;
        $resolvedEnd = $endDate;

        if ($mode === LeagueCalendarMode::MATCHDAYS) {
            $planning = LeagueMatchdayPlanning::tryFrom((string) $matchdayPlanning)
                ?? LeagueMatchdayPlanning::FIXED_LENGTH;

            if ($planning === LeagueMatchdayPlanning::FIXED_LENGTH) {
                $lengthDays = $matchdayLengthDays ?? 7;
                if ($lengthDays < 1 || $lengthDays > 60) {
                    throw ValidationException::withMessages([
                        'matchday_length_days' => 'Kolejka musi trwać od 1 do 60 dni.',
                    ]);
                }
                $resolvedEnd = $startDate;
            } else {
                if ($endDate === null || $endDate === '') {
                    throw ValidationException::withMessages([
                        'endDate' => 'Podaj datę zakończenia sezonu — od niej wyliczymy długość kolejki.',
                    ]);
                }
            }
        } else {
            $resolvedEnd = $deadlineAt ?: $endDate;
            if ($resolvedEnd === null || $resolvedEnd === '') {
                throw ValidationException::withMessages([
                    'deadline_at' => 'Podaj termin wszystkich meczów — to będzie też data zakończenia sezonu.',
                ]);
            }
        }

        $winMode = $allowsDraws ? MatchWinMode::BEST_OF : MatchWinMode::FIRST_TO;
        try {
            MatchFormat::forLeagueRules(501, $winMode, $winLength)->validate();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['win_length' => $e->getMessage()]);
        }

        $season = $this->leagueSeasonRepository->create([
            'league_id' => $leagueId,
            'name' => $name,
            'status' => LeagueSeasonStatus::DRAFT,
            'calendar_mode' => $mode,
            'rounds_each' => $roundsEach,
            'allows_draws' => $allowsDraws,
            'win_mode' => $winMode,
            'win_length' => $winLength,
            'matchday_length_days' => $lengthDays,
            'matchday_planning' => $planning,
            'start_date' => $startDate,
            'end_date' => Carbon::parse($resolvedEnd)->toDateString(),
            'deadline_at' => $mode === LeagueCalendarMode::DEADLINE
                ? Carbon::parse($resolvedEnd)->endOfDay()
                : null,
        ]);

        if ($startNow) {
            $this->start($season->id);
        }

        return $season->fresh();
    }

    /**
     * Anuluje sezon: przywraca skład piramidy ze zdjęcia startowego i kasuje mecze / kolejki / sezon.
     */
    public function cancel(int $seasonId): int
    {
        return DB::transaction(function () use ($seasonId) {
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
            $leagueId = $season->league_id;

            if ($season->status !== LeagueSeasonStatus::DRAFT) {
                $roster = $this->leagueSeasonRepository->snapshotRosterByLiveDivision($season);
                $this->leagueRepository->replaceRoster($leagueId, $roster);
            }

            $this->leagueSeasonRepository->delete($season->id);

            return $leagueId;
        });
    }

    public function start(int $seasonId): void
    {
        DB::transaction(function () use ($seasonId) {
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
            if ($season->status !== LeagueSeasonStatus::DRAFT) {
                throw new DomainException('Sezon ligowy można wystartować tylko ze szkicu.');
            }

            $league = $this->leagueRepository->findWithRoster($season->league_id);
            if ($league->divisions->isEmpty()) {
                throw new DomainException('Liga nie ma szczebli.');
            }

            $divisionsPayload = [];
            $participantsPayload = [];
            foreach ($league->divisions as $division) {
                $divisionsPayload[] = [
                    'league_division_id' => $division->id,
                    'position' => $division->position,
                    'name' => $division->name,
                    'capacity' => $division->capacity,
                    'starting_score' => $division->starting_score,
                    'legs_to_win_set' => $this->seasonMatchFormat($season, (int) $division->starting_score)->legsToWinSet,
                    'sets_to_win_match' => 1,
                    'game_type' => $division->game_type,
                    'promote_direct' => $division->promote_direct,
                    'promote_playoff' => $division->promote_playoff,
                ];
                foreach ($division->members as $member) {
                    $participantsPayload[] = [
                        'league_season_division_position' => $division->position,
                        'player_id' => $member->player_id,
                    ];
                }
            }

            $this->leagueSeasonRepository->snapshotStructure($season, $divisionsPayload, $participantsPayload);
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);

            $games = [];
            $matchdayIds = [];
            $windowEndByRound = [];
            $maxRounds = 0;
            $roundRobin = [];
            foreach ($season->divisions as $seasonDivision) {
                $playerIds = $seasonDivision->participants->pluck('player_id')->map(fn ($id) => (int) $id)->all();
                $rounds = RoundRobinScheduler::rounds($playerIds, (int) $season->rounds_each);
                $roundRobin[$seasonDivision->id] = $rounds;
                $maxRounds = max($maxRounds, count($rounds));
            }

            if ($season->calendar_mode === LeagueCalendarMode::MATCHDAYS && $maxRounds > 0) {
                $windows = $this->matchdayWindows($season, $maxRounds);
                $matchdayIds = $this->leagueSeasonRepository->createMatchdays($season, $windows);
                foreach ($windows as $window) {
                    $windowEndByRound[$window['round_number']] = $window['window_end'];
                }
                $lastEnd = $windows[$maxRounds - 1]['window_end'];
                if ($season->matchday_planning === LeagueMatchdayPlanning::FIXED_LENGTH
                    || $lastEnd->toDateString() > Carbon::parse($season->end_date)->toDateString()) {
                    $this->leagueSeasonRepository->update($season->id, [
                        'end_date' => $lastEnd->toDateString(),
                    ]);
                    $season->end_date = $lastEnd->toDateString();
                }
            }

            $deadline = $season->deadline_at ?? Carbon::parse($season->end_date)->endOfDay();

            foreach ($season->divisions as $seasonDivision) {
                $format = $this->seasonMatchFormat($season, (int) $seasonDivision->starting_score);
                foreach ($roundRobin[$seasonDivision->id] as $roundIndex => $pairs) {
                    $roundNumber = $roundIndex + 1;
                    $matchdayId = $matchdayIds[$roundNumber] ?? null;
                    $gameDeadline = $matchdayId
                        ? ($windowEndByRound[$roundNumber] ?? $deadline)
                        : $deadline;

                    foreach ($pairs as $pair) {
                        $games[] = [
                            'league_season_id' => $season->id,
                            'league_season_division_id' => $seasonDivision->id,
                            'league_season_matchday_id' => $matchdayId,
                            'purpose' => LeagueGamePurpose::REGULAR,
                            'player1_id' => $pair['player1Id'],
                            'player2_id' => $pair['player2Id'],
                            'status' => LeagueGameStatus::SCHEDULED,
                            'walkover_type' => LeagueWalkoverType::NONE,
                            'deadline_at' => $gameDeadline,
                            ...$format->toDatabaseColumns(),
                            'win_mode' => $format->winMode,
                            'win_length' => $format->winLength,
                        ];
                    }
                }
            }

            $this->leagueSeasonRepository->createGames($games);
            $this->leagueSeasonRepository->update($season->id, [
                'status' => LeagueSeasonStatus::IN_PROGRESS,
                'started_at' => now(),
                'random_seed' => random_int(1, 2_000_000_000),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(int $seasonId): array
    {
        $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
        $withdrawnIds = $season->participants->whereNotNull('withdrawn_at')->pluck('player_id')->all();
        $divisions = [];

        foreach ($season->divisions as $division) {
            $activeIds = $division->participants
                ->filter(fn ($p) => $p->withdrawn_at === null)
                ->pluck('player_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $regularGames = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $standings = LeagueStandingCalculator::calculate(
                $activeIds,
                $regularGames->map(fn (LeagueGame $game) => $this->toStandingGame($game))->all(),
                (bool) $season->allows_draws,
            );

            $divisions[] = [
                'division' => $division,
                'standings' => $standings,
                'games' => $regularGames->values(),
                'players' => $division->participants->keyBy('player_id'),
            ];
        }

        $playoffGames = $season->games->where('purpose', LeagueGamePurpose::PROMOTION_PLAYOFF)->values();
        $tiebreakGames = $season->games->where('purpose', LeagueGamePurpose::TIEBREAKER)->values();

        return [
            'season' => $season,
            'league' => $season->league,
            'organization' => $season->league->organization,
            'divisions' => $divisions,
            'playoffGames' => $playoffGames,
            'tiebreakGames' => $tiebreakGames,
            'withdrawnIds' => $withdrawnIds,
            'canAdvance' => $season->status->isOpen() && $this->regularPhaseComplete($season),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showForApi(int $seasonId): array
    {
        $data = $this->showData($seasonId);
        /** @var LeagueSeason $season */
        $season = $data['season'];
        $league = $data['league'];
        $organization = $data['organization'];
        $status = $season->status;
        $participants = $season->participants;

        $divisions = [];
        foreach ($data['divisions'] as $block) {
            $division = $block['division'];
            /** @var \Illuminate\Support\Collection<int, LeagueGame> $regularGames */
            $regularGames = $block['games'];
            $standings = [];
            foreach ($block['standings'] as $row) {
                /** @var LeagueStandingRow $row */
                $participant = $participants->firstWhere('player_id', $row->playerId);
                $standings[] = [
                    'place' => $row->place,
                    'playerId' => $row->playerId,
                    'playerName' => $participant?->player?->name ?? ('#'.$row->playerId),
                    'userId' => $participant?->player?->user_id,
                    'played' => $row->played,
                    'wins' => $row->wins,
                    'draws' => $row->draws,
                    'losses' => $row->losses,
                    'points' => $row->points,
                    'unitDiff' => $row->unitDiff,
                    'needsTiebreak' => $row->needsTiebreak,
                ];
            }

            $rounds = [];
            if ($season->matchdays->isNotEmpty()) {
                foreach ($season->matchdays as $matchday) {
                    $roundGames = $regularGames->where('league_season_matchday_id', $matchday->id)->values();
                    if ($roundGames->isEmpty()) {
                        continue;
                    }
                    $mapped = $this->mapMatchdayForApi($matchday);
                    $mapped['games'] = $roundGames->map(fn (LeagueGame $game) => $this->mapGameForApi($game))->all();
                    $rounds[] = $mapped;
                }
            }

            $divisions[] = [
                'id' => $division->id,
                'name' => $division->name,
                'position' => (int) $division->position,
                'standings' => $standings,
                'rounds' => $rounds,
                'games' => $season->matchdays->isEmpty()
                    ? $regularGames->map(fn (LeagueGame $game) => $this->mapGameForApi($game))->values()->all()
                    : [],
            ];
        }

        return [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'status' => $status->value,
                'statusLabel' => $status->label(),
                'statusVariant' => $status->isOpen()
                    ? 'live'
                    : ($status === LeagueSeasonStatus::FINISHED ? 'finished' : 'planned'),
                'calendarMode' => $season->calendar_mode->value,
                'calendarModeLabel' => $season->calendar_mode->label(),
                'matchdayPlanning' => $season->matchday_planning?->value,
                'matchdayLengthDays' => $season->matchday_length_days,
                'matchdayLengthLabel' => $season->matchday_length_days
                    ? LeagueMatchdayCalendar::lengthLabel((int) $season->matchday_length_days)
                    : null,
                'roundsEach' => (int) $season->rounds_each,
                'allowsDraws' => (bool) $season->allows_draws,
                'winMode' => $season->win_mode->value,
                'winLength' => (int) $season->win_length,
                'formatLabel' => $season->allows_draws
                    ? 'Best of '.$season->win_length.' (z remisami)'
                    : 'First to '.$season->win_length,
                'startDate' => $season->start_date?->format('Y-m-d'),
                'endDate' => $season->end_date?->format('Y-m-d'),
            ],
            'league' => [
                'id' => $league->id,
                'name' => $league->name,
            ],
            'organization' => $organization
                ? ['id' => $organization->id, 'name' => $organization->name]
                : null,
            'divisions' => $divisions,
            'tiebreakGames' => collect($data['tiebreakGames'])->map(fn (LeagueGame $game) => $this->mapGameForApi($game))->values()->all(),
            'playoffGames' => collect($data['playoffGames'])->map(fn (LeagueGame $game) => $this->mapGameForApi($game))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMatchdayForApi(\App\Models\League\LeagueSeasonMatchday $matchday): array
    {
        return [
            'id' => $matchday->id,
            'roundNumber' => (int) $matchday->round_number,
            'windowLabel' => $matchday->windowLabel(),
            'isCurrent' => $matchday->isCurrent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGameForApi(LeagueGame $game): array
    {
        return [
            'id' => $game->id,
            'player1' => [
                'id' => $game->player1_id,
                'name' => $game->player1?->name ?? ('#'.$game->player1_id),
                'userId' => $game->player1?->user_id,
            ],
            'player2' => [
                'id' => $game->player2_id,
                'name' => $game->player2?->name ?? ('#'.$game->player2_id),
                'userId' => $game->player2?->user_id,
            ],
            'player1Score' => $game->player1_score,
            'player2Score' => $game->player2_score,
            'status' => $game->status->value,
            'isThirdPlace' => (bool) $game->is_third_place,
            'matchdayId' => $game->league_season_matchday_id,
        ];
    }

    public function recordResult(int $gameId, int $player1Score, int $player2Score): void
    {
        $game = $this->requireOpenGame($gameId);
        $format = MatchFormat::fromRecord($game);
        try {
            $winnerId = GameLegScoreValidator::validateAndResolveWinner(
                $game->player1_id,
                $game->player2_id,
                $player1Score,
                $player2Score,
                $format,
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['player1_score' => $e->getMessage()]);
        }

        $this->leagueSeasonRepository->updateGame($game->id, [
            'player1_score' => $player1Score,
            'player2_score' => $player2Score,
            'winner_id' => $winnerId,
            'status' => LeagueGameStatus::FINISHED,
            'walkover_type' => LeagueWalkoverType::NONE,
        ]);
    }

    public function recordWalkover(int $gameId, string $type, ?int $winnerPlayerId): void
    {
        $game = $this->requireOpenGame($gameId);
        $walkover = LeagueWalkoverType::from($type);
        $format = MatchFormat::fromRecord($game);

        if ($walkover === LeagueWalkoverType::BOTH) {
            $this->leagueSeasonRepository->updateGame($game->id, [
                'player1_score' => 0,
                'player2_score' => 0,
                'winner_id' => null,
                'status' => LeagueGameStatus::FINISHED,
                'walkover_type' => LeagueWalkoverType::BOTH,
            ]);

            return;
        }

        if ($winnerPlayerId === null || ! in_array($winnerPlayerId, [$game->player1_id, $game->player2_id], true)) {
            throw ValidationException::withMessages(['winner_id' => 'Wskaż zwycięzcę walkowera.']);
        }

        [$s1, $s2] = GameLegScoreValidator::walkoverScores($winnerPlayerId, $game->player1_id, $format);
        $this->leagueSeasonRepository->updateGame($game->id, [
            'player1_score' => $s1,
            'player2_score' => $s2,
            'winner_id' => $winnerPlayerId,
            'status' => LeagueGameStatus::FINISHED,
            'walkover_type' => LeagueWalkoverType::SINGLE,
        ]);
    }

    public function extendGame(int $gameId, string $deadlineAt): void
    {
        $game = $this->requireOpenGame($gameId);
        $this->leagueSeasonRepository->extendGameDeadline($game->id, Carbon::parse($deadlineAt)->endOfDay());
    }

    public function withdraw(int $seasonId, int $playerId): void
    {
        DB::transaction(function () use ($seasonId, $playerId) {
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
            if (! $season->status->isOpen()) {
                throw new DomainException('Rezygnacja jest możliwa tylko w trakcie sezonu.');
            }
            $participant = $season->participants->firstWhere('player_id', $playerId);
            if ($participant === null || $participant->withdrawn_at !== null) {
                throw ValidationException::withMessages(['player_id' => 'Ten zawodnik nie jest aktywny w sezonie.']);
            }

            $this->leagueSeasonRepository->voidGamesForPlayer($season->id, $playerId);
            $this->leagueSeasonRepository->markWithdrawn($season->id, $playerId, now());
            $this->leagueRepository->removePlayer($season->league_id, $playerId);
        });
    }

    /**
     * Następny krok cyklu: dogrywki → baraże → zamknięcie z aktualizacją piramidy.
     */
    public function advance(int $seasonId): string
    {
        return DB::transaction(function () use ($seasonId) {
            $season = $this->leagueSeasonRepository->findWithGraph($seasonId);
            if (! $season->status->isOpen()) {
                throw new DomainException('Ten sezon nie jest w toku.');
            }
            if (! $this->regularPhaseComplete($season)) {
                throw new DomainException('Najpierw dokończ wszystkie mecze fazy zasadniczej.');
            }

            $openTiebreak = $season->games
                ->where('purpose', LeagueGamePurpose::TIEBREAKER)
                ->where('status', LeagueGameStatus::SCHEDULED);
            if ($openTiebreak->isNotEmpty()) {
                throw new DomainException('Najpierw dokończ dogrywki.');
            }

            $createdTiebreaks = $this->generateNeededTiebreaks($season);
            if ($createdTiebreaks > 0) {
                return 'Wygenerowano dogrywki do rozstrzygnięcia tabeli.';
            }

            $standings = $this->uniqueStandingsByDivision($season);

            if ($season->status === LeagueSeasonStatus::IN_PROGRESS) {
                $plan = $this->promotionPlan($season, $standings, []);
                if ($plan['playoffPairings'] !== []) {
                    $this->createPlayoffGames($season, $plan['playoffPairings']);
                    $this->leagueSeasonRepository->update($season->id, [
                        'status' => LeagueSeasonStatus::PLAYOFFS,
                    ]);

                    return 'Faza zasadnicza zamknięta — wygenerowano baraże.';
                }

                $this->finalizeRoster($season, $plan['rosterByDivisionId']);

                return 'Sezon ligowy zakończony. Piramida zaktualizowana.';
            }

            $openPlayoffs = $season->games
                ->where('purpose', LeagueGamePurpose::PROMOTION_PLAYOFF)
                ->where('status', LeagueGameStatus::SCHEDULED);
            if ($openPlayoffs->isNotEmpty()) {
                throw new DomainException('Najpierw dokończ baraże.');
            }

            $finishedPlayoffs = $season->games
                ->where('purpose', LeagueGamePurpose::PROMOTION_PLAYOFF)
                ->where('status', LeagueGameStatus::FINISHED)
                ->map(fn (LeagueGame $game) => [
                    'higherDivisionId' => (int) $game->higher_season_division_id,
                    'lowerDivisionId' => (int) $game->lower_season_division_id,
                    'winnerId' => (int) $game->winner_id,
                    'loserId' => (int) $game->winner_id === (int) $game->player1_id
                        ? (int) $game->player2_id
                        : (int) $game->player1_id,
                ])
                ->all();

            $plan = $this->promotionPlan($season, $standings, $finishedPlayoffs);
            $this->finalizeRoster($season, $plan['rosterByDivisionId']);

            return 'Sezon ligowy zakończony. Piramida zaktualizowana.';
        });
    }

    public function gameShowData(int $gameId): array
    {
        $game = $this->leagueSeasonRepository->findGame($gameId);
        $format = MatchFormat::fromRecord($game);

        return [
            'game' => $game,
            'season' => $game->season,
            'league' => $game->season->league,
            'organization' => $game->season->league->organization,
            'format' => $format,
            'canManage' => $game->season->status->isOpen()
                && $game->status !== LeagueGameStatus::VOIDED
                && ! in_array($game->status, [LeagueGameStatus::LOBBY, LeagueGameStatus::IN_PROGRESS], true),
        ];
    }

    private function requireOpenGame(int $gameId): LeagueGame
    {
        $game = $this->leagueSeasonRepository->findGame($gameId);
        if (! $game->season->status->isOpen()) {
            throw new DomainException('Sezon jest zamknięty.');
        }
        if ($game->status === LeagueGameStatus::VOIDED) {
            throw new DomainException('Ten mecz został anulowany (rezygnacja).');
        }
        if (in_array($game->status, [LeagueGameStatus::LOBBY, LeagueGameStatus::IN_PROGRESS], true)) {
            throw new DomainException('Mecz jest w lobby albo w trakcie — nie wpisuj wyniku ręcznie.');
        }

        return $game;
    }

    private function regularPhaseComplete(LeagueSeason $season): bool
    {
        return $season->games
            ->where('purpose', LeagueGamePurpose::REGULAR)
            ->filter(fn (LeagueGame $game) => ! in_array(
                $game->status,
                [LeagueGameStatus::FINISHED, LeagueGameStatus::VOIDED],
                true,
            ))
            ->isEmpty();
    }

    /**
     * @param  LeagueSeason  $season  with graph
     */
    private function generateNeededTiebreaks(LeagueSeason $season): int
    {
        $created = 0;
        $snapshots = $this->snapshots($season);
        $divisions = $season->divisions->sortBy('position')->values();

        foreach ($divisions as $index => $division) {
            $activeIds = $this->activePlayerIds($division);
            $regular = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $rows = LeagueStandingCalculator::calculate(
                $activeIds,
                $regular->map(fn (LeagueGame $game) => $this->toStandingGame($game))->all(),
                (bool) $season->allows_draws,
            );
            $higher = $index === 0 ? null : $snapshots[$divisions[$index - 1]->id];
            $lower = $index === $divisions->count() - 1 ? null : $snapshots[$divisions[$index + 1]->id];
            $snapshot = $snapshots[$division->id];

            if (! LeaguePromotionResolver::tieAffectsCut($rows, $snapshot, $higher, $lower)) {
                continue;
            }

            $groups = [];
            foreach ($rows as $row) {
                if ($row->needsTiebreak && $row->tieGroupKey) {
                    $groups[$row->tieGroupKey][] = $row;
                }
            }

            foreach ($groups as $key => $groupRows) {
                usort($groupRows, static fn (LeagueStandingRow $a, LeagueStandingRow $b) => $a->playerId <=> $b->playerId);
                $seeded = array_map(static fn (LeagueStandingRow $row) => $row->playerId, $groupRows);
                $existing = $season->games
                    ->where('purpose', LeagueGamePurpose::TIEBREAKER)
                    ->where('tie_group_key', $key)
                    ->where('league_season_division_id', $division->id);

                if (count($seeded) > 4) {
                    $created += $this->ensureRoundRobinTiebreak($season, $division, $key, $seeded, $existing);
                    continue;
                }

                $bracketGames = $existing->map(fn (LeagueGame $game) => [
                    'player1Id' => $game->player1_id,
                    'player2Id' => $game->player2_id,
                    'winnerId' => $game->winner_id,
                    'status' => $game->status->value,
                    'bracketRound' => (int) $game->bracket_round,
                    'isThirdPlace' => (bool) $game->is_third_place,
                ])->all();

                $progress = LeagueTieBreakBracket::next($seeded, $bracketGames);
                foreach ($progress['pending'] as $pending) {
                    $already = $existing->contains(function (LeagueGame $game) use ($pending) {
                        $pair = [$game->player1_id, $game->player2_id];

                        return in_array($pending['player1Id'], $pair, true)
                            && in_array($pending['player2Id'], $pair, true)
                            && (int) $game->bracket_round === $pending['bracketRound']
                            && (bool) $game->is_third_place === $pending['isThirdPlace'];
                    });
                    if ($already) {
                        continue;
                    }
                    $this->createTiebreakGame($season, $division, $key, $pending);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  \Illuminate\Support\Collection<int, LeagueGame>  $existing
     */
    private function ensureRoundRobinTiebreak(
        LeagueSeason $season,
        LeagueSeasonDivision $division,
        string $key,
        array $playerIds,
        $existing,
    ): int {
        $created = 0;
        foreach (RoundRobinScheduler::rounds($playerIds, 1) as $pairs) {
            foreach ($pairs as $pair) {
                $exists = $existing->contains(function (LeagueGame $game) use ($pair) {
                    $ids = [$game->player1_id, $game->player2_id];

                    return in_array($pair['player1Id'], $ids, true) && in_array($pair['player2Id'], $ids, true);
                });
                if ($exists) {
                    continue;
                }
                $this->createTiebreakGame($season, $division, $key, [
                    'player1Id' => $pair['player1Id'],
                    'player2Id' => $pair['player2Id'],
                    'bracketRound' => 1,
                    'isThirdPlace' => false,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param  array{player1Id: int, player2Id: int, bracketRound: int, isThirdPlace: bool}  $pending
     */
    private function createTiebreakGame(
        LeagueSeason $season,
        LeagueSeasonDivision $division,
        string $key,
        array $pending,
    ): void {
        $format = $this->seasonMatchFormat($season, (int) $division->starting_score, mustHaveWinner: true);
        $this->leagueSeasonRepository->createGames([[
            'league_season_id' => $season->id,
            'league_season_division_id' => $division->id,
            'purpose' => LeagueGamePurpose::TIEBREAKER,
            'player1_id' => $pending['player1Id'],
            'player2_id' => $pending['player2Id'],
            'status' => LeagueGameStatus::SCHEDULED,
            'walkover_type' => LeagueWalkoverType::NONE,
            'deadline_at' => $season->deadline_at ?? Carbon::parse($season->end_date)->endOfDay(),
            'tie_group_key' => $key,
            'bracket_round' => $pending['bracketRound'],
            'is_third_place' => $pending['isThirdPlace'],
            ...$this->formatColumns($format),
        ]]);
    }

    /**
     * @return array<int, list<LeagueStandingRow>>
     */
    private function uniqueStandingsByDivision(LeagueSeason $season): array
    {
        $out = [];
        $seed = (int) ($season->random_seed ?: $season->id);

        foreach ($season->divisions as $division) {
            $activeIds = $this->activePlayerIds($division);
            $regular = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $rows = LeagueStandingCalculator::calculate(
                $activeIds,
                $regular->map(fn (LeagueGame $game) => $this->toStandingGame($game))->all(),
                (bool) $season->allows_draws,
            );

            $resolved = [];
            $index = 0;
            while ($index < count($rows)) {
                $row = $rows[$index];
                if (! $row->needsTiebreak || $row->tieGroupKey === null) {
                    $resolved[] = $row;
                    $index++;
                    continue;
                }
                $group = [];
                $j = $index;
                while ($j < count($rows) && $rows[$j]->tieGroupKey === $row->tieGroupKey) {
                    $group[] = $rows[$j];
                    $j++;
                }
                $order = $this->resolvedTieOrder($season, $division, $row->tieGroupKey, $group, $seed);
                foreach ($order as $playerId) {
                    foreach ($group as $member) {
                        if ($member->playerId === $playerId) {
                            $resolved[] = $member->withPlace(0, false, null);
                            break;
                        }
                    }
                }
                $index = $j;
            }

            $placed = [];
            foreach ($resolved as $i => $item) {
                $placed[] = $item->withPlace($i + 1, false, null);
            }
            $out[$division->id] = $placed;
        }

        return $out;
    }

    /**
     * @param  list<LeagueStandingRow>  $group
     * @return list<int>
     */
    private function resolvedTieOrder(
        LeagueSeason $season,
        LeagueSeasonDivision $division,
        string $key,
        array $group,
        int $seed,
    ): array {
        $seeded = array_map(static fn (LeagueStandingRow $row) => $row->playerId, $group);
        $games = $season->games
            ->where('purpose', LeagueGamePurpose::TIEBREAKER)
            ->where('tie_group_key', $key)
            ->where('league_season_division_id', $division->id);

        if (count($seeded) <= 4) {
            $progress = LeagueTieBreakBracket::next($seeded, $games->map(fn (LeagueGame $game) => [
                'player1Id' => $game->player1_id,
                'player2Id' => $game->player2_id,
                'winnerId' => $game->winner_id,
                'status' => $game->status->value,
                'bracketRound' => (int) $game->bracket_round,
                'isThirdPlace' => (bool) $game->is_third_place,
            ])->all());
            if ($progress['ordered'] !== null) {
                return $progress['ordered'];
            }
        }

        if ($games->where('status', LeagueGameStatus::FINISHED)->isNotEmpty()) {
            $mini = LeagueStandingCalculator::calculate(
                $seeded,
                $games->map(fn (LeagueGame $game) => $this->toStandingGame($game))->all(),
            );
            $mini = LeagueStandingCalculator::breakRemainingTiesWithLottery($mini, $seed);

            return array_map(static fn (LeagueStandingRow $row) => $row->playerId, $mini);
        }

        $lottery = LeagueStandingCalculator::breakRemainingTiesWithLottery($group, $seed);

        return array_map(static fn (LeagueStandingRow $row) => $row->playerId, $lottery);
    }

    /**
     * @param  array<int, list<LeagueStandingRow>>  $standings
     * @param  list<array{higherDivisionId: int, lowerDivisionId: int, winnerId: int, loserId: int}>  $finishedPlayoffs
     * @return array{playoffPairings: list<LeaguePlayoffPairing>, rosterByDivisionId: array<int, list<int>>}
     */
    private function promotionPlan(LeagueSeason $season, array $standings, array $finishedPlayoffs): array
    {
        $snapshots = [];
        foreach ($season->divisions as $division) {
            $snapshots[] = new LeagueDivisionSnapshot(
                id: $division->id,
                position: $division->position,
                name: $division->name,
                capacity: $division->capacity,
                promoteDirect: $division->promote_direct,
                promotePlayoff: $division->promote_playoff,
                playerIds: $this->activePlayerIds($division),
            );
        }

        return LeaguePromotionResolver::resolve($snapshots, $standings, $finishedPlayoffs);
    }

    /**
     * @param  list<LeaguePlayoffPairing>  $pairings
     */
    private function createPlayoffGames(LeagueSeason $season, array $pairings): void
    {
        $games = [];
        $divisions = $season->divisions->keyBy('id');
        foreach ($pairings as $pairing) {
            $higher = $divisions->get($pairing->higherDivisionId);
            $format = $this->seasonMatchFormat($season, (int) $higher->starting_score, mustHaveWinner: true);
            $games[] = [
                'league_season_id' => $season->id,
                'league_season_division_id' => $higher->id,
                'higher_season_division_id' => $pairing->higherDivisionId,
                'lower_season_division_id' => $pairing->lowerDivisionId,
                'purpose' => LeagueGamePurpose::PROMOTION_PLAYOFF,
                'player1_id' => $pairing->higherPlayerId,
                'player2_id' => $pairing->lowerPlayerId,
                'status' => LeagueGameStatus::SCHEDULED,
                'walkover_type' => LeagueWalkoverType::NONE,
                'deadline_at' => $season->deadline_at ?? Carbon::parse($season->end_date)->endOfDay(),
                ...$this->formatColumns($format),
            ];
        }
        $this->leagueSeasonRepository->createGames($games);
    }

    /**
     * @param  array<int, list<int>>  $rosterBySeasonDivisionId
     */
    private function finalizeRoster(LeagueSeason $season, array $rosterBySeasonDivisionId): void
    {
        $byLiveDivision = [];
        foreach ($season->divisions as $division) {
            $liveId = $division->league_division_id;
            if ($liveId === null) {
                continue;
            }
            $byLiveDivision[$liveId] = $rosterBySeasonDivisionId[$division->id] ?? [];
        }
        $this->leagueRepository->replaceRoster($season->league_id, $byLiveDivision);
        $this->leagueSeasonRepository->update($season->id, [
            'status' => LeagueSeasonStatus::FINISHED,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<int, LeagueDivisionSnapshot>
     */
    private function snapshots(LeagueSeason $season): array
    {
        $out = [];
        foreach ($season->divisions as $division) {
            $out[$division->id] = new LeagueDivisionSnapshot(
                id: $division->id,
                position: $division->position,
                name: $division->name,
                capacity: $division->capacity,
                promoteDirect: $division->promote_direct,
                promotePlayoff: $division->promote_playoff,
                playerIds: $this->activePlayerIds($division),
            );
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function activePlayerIds(LeagueSeasonDivision $division): array
    {
        return $division->participants
            ->filter(fn ($participant) => $participant->withdrawn_at === null)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{round_number: int, window_start: \Carbon\CarbonInterface, window_end: \Carbon\CarbonInterface}>
     */
    private function matchdayWindows(LeagueSeason $season, int $roundCount): array
    {
        if ($season->matchday_planning === LeagueMatchdayPlanning::EQUAL_SPAN) {
            return LeagueMatchdayCalendar::equalSpanWindows(
                Carbon::parse($season->start_date)->startOfDay(),
                Carbon::parse($season->end_date)->endOfDay(),
                $roundCount,
            );
        }

        $lengthDays = (int) ($season->matchday_length_days ?: 7);

        return LeagueMatchdayCalendar::windows(
            Carbon::parse($season->start_date)->startOfDay(),
            $lengthDays,
            $roundCount,
        );
    }

    /**
     * @return array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}
     */
    private function toStandingGame(LeagueGame $game): array
    {
        return [
            'player1Id' => $game->player1_id,
            'player2Id' => $game->player2_id,
            'player1Score' => (int) ($game->player1_score ?? 0),
            'player2Score' => (int) ($game->player2_score ?? 0),
            'winnerId' => $game->winner_id,
            'status' => $game->status->value,
            'walkoverType' => $game->walkover_type->value,
        ];
    }

    private function seasonMatchFormat(LeagueSeason $season, int $startingScore, bool $mustHaveWinner = false): MatchFormat
    {
        if ($mustHaveWinner || ! $season->allows_draws) {
            $length = $season->allows_draws
                ? intdiv((int) $season->win_length, 2) + 1
                : (int) $season->win_length;

            return MatchFormat::forLeagueRules($startingScore, MatchWinMode::FIRST_TO, $length);
        }

        return MatchFormat::forLeagueRules($startingScore, $season->win_mode, (int) $season->win_length);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatColumns(MatchFormat $format): array
    {
        return [
            ...$format->toDatabaseColumns(),
            'win_mode' => $format->winMode,
            'win_length' => $format->winLength,
        ];
    }
}
