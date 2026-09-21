<?php

namespace App\Services\League;

use App\Domain\League\LeagueDivisionSnapshot;
use App\Domain\League\LeaguePlayoffPairing;
use App\Domain\League\LeaguePromotionResolver;
use App\Domain\League\LeagueStandingCalculator;
use App\Domain\League\LeagueStandingRow;
use App\Domain\League\LeagueTieBreakBracket;
use App\Domain\League\RoundRobinScheduler;
use App\Enums\LeagueGamePurpose;
use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\LeagueWalkoverType;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;
use App\Repositories\League\LeagueRepository;
use App\Repositories\League\LeagueSeasonRepository;
use App\Services\Player\PlayerOverviewService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class LeagueSeasonAdvanceService
{
    public function __construct(
        private LeagueRepository $leagueRepository,
        private LeagueSeasonRepository $leagueSeasonRepository,
        private LeagueSeasonStandingsBuilder $standings,
        private PlayerOverviewService $playerOverviewService,
    ) {}

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
            if (! $this->standings->regularPhaseComplete($season)) {
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

                $this->finalizeRoster($season, $plan['rosterByDivisionId'], $standings);

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
            $this->finalizeRoster($season, $plan['rosterByDivisionId'], $standings);

            return 'Sezon ligowy zakończony. Piramida zaktualizowana.';
        });
    }

    public function championPlayerId(LeagueSeason $season): ?int
    {
        return $this->championPlayerIdFromStandings($season, $this->uniqueStandingsByDivision($season));
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
            $activeIds = $this->standings->activePlayerIds($division);
            $regular = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $rows = LeagueStandingCalculator::calculate(
                $activeIds,
                $regular->map(fn (LeagueGame $game) => $this->standings->toStandingGame($game))->all(),
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
        $format = $this->standings->seasonMatchFormat(
            $season,
            (int) $division->starting_score,
            mustHaveWinner: true,
            dartLimit: $division->dart_limit !== null ? (int) $division->dart_limit : null,
            lossThreshold: $division->loss_threshold !== null ? (int) $division->loss_threshold : null,
        );
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
            ...$this->standings->formatColumns($format),
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
            $activeIds = $this->standings->activePlayerIds($division);
            $regular = $season->games
                ->where('league_season_division_id', $division->id)
                ->where('purpose', LeagueGamePurpose::REGULAR);
            $rows = LeagueStandingCalculator::calculate(
                $activeIds,
                $regular->map(fn (LeagueGame $game) => $this->standings->toStandingGame($game))->all(),
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
                $games->map(fn (LeagueGame $game) => $this->standings->toStandingGame($game))->all(),
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
                playerIds: $this->standings->activePlayerIds($division),
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
            $format = $this->standings->seasonMatchFormat(
                $season,
                (int) $higher->starting_score,
                mustHaveWinner: true,
                dartLimit: $higher->dart_limit !== null ? (int) $higher->dart_limit : null,
                lossThreshold: $higher->loss_threshold !== null ? (int) $higher->loss_threshold : null,
            );
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
                ...$this->standings->formatColumns($format),
            ];
        }
        $this->leagueSeasonRepository->createGames($games);
    }

    /**
     * @param  array<int, list<int>>  $rosterBySeasonDivisionId
     * @param  array<int, list<\App\Domain\League\LeagueStandingRow>>  $standingsByDivisionId
     */
    private function finalizeRoster(LeagueSeason $season, array $rosterBySeasonDivisionId, array $standingsByDivisionId): void
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
            'champion_player_id' => $this->championPlayerIdFromStandings($season, $standingsByDivisionId),
        ]);
        $playerIds = collect($rosterBySeasonDivisionId)
            ->flatten()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->playerOverviewService->rebuildRegistered($playerIds);
    }

    /**
     * Mistrz sezonu: 1. miejsce najwyższego szczebla (position = 0).
     *
     * @param  array<int, list<\App\Domain\League\LeagueStandingRow>>  $standingsByDivisionId
     */
    private function championPlayerIdFromStandings(LeagueSeason $season, array $standingsByDivisionId): ?int
    {
        $top = $season->divisions->first(fn ($division) => (int) $division->position === 0);
        if ($top === null) {
            return null;
        }

        foreach ($standingsByDivisionId[$top->id] ?? [] as $row) {
            if ((int) $row->place === 1) {
                return (int) $row->playerId;
            }
        }

        return null;
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
                playerIds: $this->standings->activePlayerIds($division),
            );
        }

        return $out;
    }
}
