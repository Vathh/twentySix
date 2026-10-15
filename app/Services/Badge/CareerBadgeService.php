<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Career\CareerCatalog;
use App\Domain\Badge\Career\CareerTournamentAward;
use App\Domain\Badge\Career\WinStreakCalculator;
use App\Enums\GameKind;
use App\Repositories\Badge\CareerBadgeReadRepository;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Player\PlayerRepository;
use Illuminate\Support\Facades\DB;

class CareerBadgeService
{
    public function __construct(
        private CareerBadgeReadRepository $careerBadgeReadRepository,
        private PlayerBadgeRepository $playerBadgeRepository,
        private PlayerRepository $playerRepository,
    ) {}

    /**
     * Podium, udział i tytuł z aktualnych miejsc. Drugie wywołanie nie podwaja licznika.
     */
    public function syncFinishedTournament(int $tournamentId): void
    {
        if (! $this->careerBadgeReadRepository->tournamentIsFinished($tournamentId)) {
            return;
        }

        $places = $this->careerBadgeReadRepository->places($tournamentId);
        $events = [];

        foreach ($this->careerBadgeReadRepository->playedPlayerIds($tournamentId) as $playerId) {
            if ($this->playerRepository->isByePlayerId($playerId)) {
                continue;
            }

            foreach (CareerTournamentAward::keysForPlace($places[$playerId] ?? null) as $badgeKey) {
                $events[] = [
                    'playerId' => $playerId,
                    'category' => BadgeCategory::Career->value,
                    'badgeKey' => $badgeKey,
                    'amount' => 1,
                ];
            }
        }

        DB::transaction(function () use ($tournamentId, $events) {
            $deleted = $this->playerBadgeRepository->deleteEventsForSource(
                CareerCatalog::SOURCE_KIND,
                $tournamentId,
            );
            $this->playerBadgeRepository->insertEvents(CareerCatalog::SOURCE_KIND, $tournamentId, $events);
            $this->recount($deleted, $events);
        });
    }

    public function syncWinStreak(int $playerId): void
    {
        if ($this->playerRepository->isByePlayerId($playerId)) {
            return;
        }

        $best = WinStreakCalculator::fromWins(
            $this->careerBadgeReadRepository->winsInOrder($playerId),
        )['best'];

        $existing = $this->playerBadgeRepository->findOne(
            $playerId,
            BadgeCategory::Career->value,
            CareerCatalog::WIN_STREAK,
        );

        if ($best < 1) {
            $this->playerBadgeRepository->replaceTotals(
                $playerId,
                BadgeCategory::Career->value,
                CareerCatalog::WIN_STREAK,
                0,
                null,
                null,
            );

            return;
        }

        $now = now()->toDateTimeString();
        $unchanged = $existing !== null && (int) $existing->times_earned === $best;

        $this->playerBadgeRepository->replaceTotals(
            $playerId,
            BadgeCategory::Career->value,
            CareerCatalog::WIN_STREAK,
            $best,
            $existing?->first_unlocked_at?->toDateTimeString() ?? $now,
            $unchanged ? ($existing->last_earned_at?->toDateTimeString() ?? $now) : $now,
        );
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function syncWinStreakForPlayers(array $playerIds): void
    {
        $seen = [];
        foreach ($playerIds as $playerId) {
            $playerId = (int) $playerId;
            if ($playerId < 1 || isset($seen[$playerId])) {
                continue;
            }
            $seen[$playerId] = true;
            $this->syncWinStreak($playerId);
        }
    }

    public function syncAfterScoredGame(GameKind $kind, int $player1Id, int $player2Id, ?int $tournamentId): void
    {
        if (! in_array($kind, [GameKind::GROUP, GameKind::PLAYOFF, GameKind::LEAGUE], true)) {
            return;
        }

        $this->syncWinStreakForPlayers([$player1Id, $player2Id]);

        if ($tournamentId !== null && $kind !== GameKind::LEAGUE) {
            $this->syncFinishedTournament($tournamentId);
        }
    }

    /**
     * Po korekcie playoff przelicza wszystkich wpisanych w turnieju, bo reset gałęzi
     * zabiera zakończone mecze także dalszym zawodnikom.
     */
    public function syncAfterTournamentGame(
        int $tournamentId,
        int $player1Id,
        int $player2Id,
        bool $includeAllParticipants,
    ): void {
        $playerIds = $includeAllParticipants
            ? $this->careerBadgeReadRepository->participantIds($tournamentId)
            : [$player1Id, $player2Id];

        $this->syncWinStreakForPlayers($playerIds);
        $this->syncFinishedTournament($tournamentId);
    }

    /**
     * @param  list<int>  $gameIds
     */
    public function syncWinStreakForLeagueGames(array $gameIds): void
    {
        $this->syncWinStreakForPlayers(
            $this->careerBadgeReadRepository->playerIdsOnLeagueGames($gameIds),
        );
    }

    /**
     * @return list<int>
     */
    public function playerIdsInLeagueSeason(int $seasonId): array
    {
        return $this->careerBadgeReadRepository->playerIdsInLeagueSeason($seasonId);
    }

    /**
     * @return array{tournaments: int, players: int}
     */
    public function backfill(): array
    {
        $tournaments = 0;
        foreach ($this->careerBadgeReadRepository->finishedTournamentIds() as $tournamentId) {
            $this->syncFinishedTournament($tournamentId);
            $tournaments++;
        }

        $playerIds = $this->careerBadgeReadRepository->streakPlayerIds();
        $this->syncWinStreakForPlayers($playerIds);

        return [
            'tournaments' => $tournaments,
            'players' => count($playerIds),
        ];
    }

    /**
     * @param  list<array{player_id: int, category: string, badge_key: string}>  $deleted
     * @param  list<array{playerId: int, category: string, badgeKey: string, amount: int}>  $events
     */
    private function recount(array $deleted, array $events): void
    {
        $touched = [];

        foreach ($deleted as $row) {
            if ($row['category'] !== BadgeCategory::Career->value) {
                continue;
            }
            $touched[$row['player_id'].'|'.$row['badge_key']] = [
                'player_id' => $row['player_id'],
                'badge_key' => $row['badge_key'],
            ];
        }

        foreach ($events as $event) {
            $touched[$event['playerId'].'|'.$event['badgeKey']] = [
                'player_id' => $event['playerId'],
                'badge_key' => $event['badgeKey'],
            ];
        }

        foreach ($touched as $row) {
            $totals = $this->playerBadgeRepository->remainingTotals(
                $row['player_id'],
                BadgeCategory::Career->value,
                $row['badge_key'],
            );
            $this->playerBadgeRepository->replaceTotals(
                $row['player_id'],
                BadgeCategory::Career->value,
                $row['badge_key'],
                $totals['amount'],
                $totals['first'],
                $totals['last'],
            );
        }
    }
}
