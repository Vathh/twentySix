<?php

namespace App\Services\League;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\League\LeagueMatchdayCalendar;
use App\Domain\League\LeagueSeasonStartReadiness;
use App\Domain\League\RoundRobinScheduler;
use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueMatchdayPlanning;
use App\Enums\LeagueSeasonStatus;
use App\Enums\LeagueWalkoverType;
use App\Enums\MatchWinMode;
use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Repositories\League\LeagueRepository;
use App\Repositories\League\LeagueSeasonRepository;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeagueSeasonLifecycleService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private LeagueSeasonRepository $leagueSeasonRepository,
        private LeagueSeasonStandingsBuilder $standings,
        private LeagueSeasonAdvanceService $advance,
    ) {}

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

        return DB::transaction(function () use (
            $leagueId,
            $name,
            $mode,
            $roundsEach,
            $allowsDraws,
            $winMode,
            $winLength,
            $lengthDays,
            $planning,
            $startDate,
            $resolvedEnd,
            $startNow,
        ) {
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
        });
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
            $this->startReadinessFromLeague($league)->assertCanStart();

            $divisionsPayload = [];
            $participantsPayload = [];
            foreach ($league->divisions as $division) {
                $divisionsPayload[] = [
                    'league_division_id' => $division->id,
                    'position' => $division->position,
                    'name' => $division->name,
                    'capacity' => $division->capacity,
                    'starting_score' => $division->starting_score,
                    'legs_to_win_set' => $this->standings->seasonMatchFormat($season, (int) $division->starting_score)->legsToWinSet,
                    'sets_to_win_match' => 1,
                    'game_type' => $division->game_type,
                    'promote_direct' => $division->promote_direct,
                    'promote_playoff' => $division->promote_playoff,
                    'dart_limit' => $division->dart_limit,
                    'loss_threshold' => $division->loss_threshold,
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
                $format = $this->standings->seasonMatchFormat(
                    $season,
                    (int) $seasonDivision->starting_score,
                    dartLimit: $seasonDivision->dart_limit !== null ? (int) $seasonDivision->dart_limit : null,
                    lossThreshold: $seasonDivision->loss_threshold !== null ? (int) $seasonDivision->loss_threshold : null,
                );
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

    public function startReadinessForLeague(int $leagueId): LeagueSeasonStartReadiness
    {
        return $this->startReadinessFromLeague($this->leagueRepository->findWithRoster($leagueId));
    }

    /**
     * Uzupełnia champion_player_id dla zakończonych sezonów bez mistrza.
     */
    public function backfillFinishedSeasonChampions(): int
    {
        $count = 0;
        foreach ($this->leagueSeasonRepository->listFinishedWithoutChampion() as $season) {
            $championId = $this->advance->championPlayerId($season);
            if ($championId === null) {
                continue;
            }
            $this->leagueSeasonRepository->update($season->id, ['champion_player_id' => $championId]);
            $count++;
        }

        return $count;
    }

    private function startReadinessFromLeague(League $league): LeagueSeasonStartReadiness
    {
        $rows = [];
        foreach ($league->divisions as $division) {
            $rows[] = [
                'name' => (string) $division->name,
                'capacity' => (int) $division->capacity,
                'memberCount' => $division->members->count(),
            ];
        }

        return LeagueSeasonStartReadiness::inspect($rows);
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
}
