<?php

namespace App\Services\League;

use App\Domain\League\LeagueSeasonStartReadiness;
use App\Domain\League\LeagueStandingRow;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;

/**
 * Publiczny API sezonu ligowego — deleguje do serwisów use-case.
 */
class LeagueSeasonService
{
    public function __construct(
        private LeagueSeasonLifecycleService $lifecycle,
        private LeagueSeasonResultService $results,
        private LeagueSeasonAdvanceService $advanceService,
        private LeagueSeasonQueryService $query,
    ) {}

    public function getForPolicy(int $seasonId): LeagueSeason
    {
        return $this->query->getForPolicy($seasonId);
    }

    public function getGameForPolicy(int $gameId): LeagueGame
    {
        return $this->results->getGameForPolicy($gameId);
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
        return $this->lifecycle->create(
            $leagueId,
            $name,
            $calendarMode,
            $roundsEach,
            $startDate,
            $endDate,
            $deadlineAt,
            $startNow,
            $matchdayLengthDays,
            $matchdayPlanning,
            $allowsDraws,
            $winLength,
        );
    }

    public function cancel(int $seasonId): int
    {
        return $this->lifecycle->cancel($seasonId);
    }

    public function start(int $seasonId): void
    {
        $this->lifecycle->start($seasonId);
    }

    /**
     * @return array<string, mixed>
     */
    public function showData(int $seasonId): array
    {
        return $this->query->showData($seasonId);
    }

    public function startReadinessForLeague(int $leagueId): LeagueSeasonStartReadiness
    {
        return $this->lifecycle->startReadinessForLeague($leagueId);
    }

    /**
     * @return list<array{division: LeagueSeasonDivision, standings: list<LeagueStandingRow>, games: \Illuminate\Support\Collection<int, LeagueGame>, players: \Illuminate\Support\Collection}>
     */
    public function divisionBlocks(LeagueSeason $season): array
    {
        return $this->query->divisionBlocks($season);
    }

    /**
     * @return array{division: LeagueSeasonDivision, standings: list<LeagueStandingRow>, games: \Illuminate\Support\Collection<int, LeagueGame>, players: \Illuminate\Support\Collection}|null
     */
    public function archiveBlockForDivision(LeagueSeason $season, int $leagueDivisionId): ?array
    {
        return $this->query->archiveBlockForDivision($season, $leagueDivisionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function showForApi(int $seasonId): array
    {
        return $this->query->showForApi($seasonId);
    }

    public function recordResult(int $gameId, int $player1Score, int $player2Score): void
    {
        $this->results->recordResult($gameId, $player1Score, $player2Score);
    }

    public function recordWalkover(int $gameId, string $type, ?int $winnerPlayerId): void
    {
        $this->results->recordWalkover($gameId, $type, $winnerPlayerId);
    }

    public function extendGame(int $gameId, string $deadlineAt): void
    {
        $this->results->extendGame($gameId, $deadlineAt);
    }

    public function withdraw(int $seasonId, int $playerId): void
    {
        $this->results->withdraw($seasonId, $playerId);
    }

    public function advance(int $seasonId): string
    {
        return $this->advanceService->advance($seasonId);
    }

    public function backfillFinishedSeasonChampions(): int
    {
        return $this->lifecycle->backfillFinishedSeasonChampions();
    }
}
