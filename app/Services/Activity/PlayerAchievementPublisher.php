<?php

namespace App\Services\Activity;

use App\Domain\Activity\BadgeUnlock;
use App\Domain\Activity\PersonalBestDiff;
use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Career\CareerCatalog;
use App\Domain\Badge\Career\WinStreakCalculator;
use App\Domain\Career\CareerSnapshotMetrics;
use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\LeagueGameStatus;
use App\Repositories\Badge\CareerBadgeReadRepository;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Career\PlayerGameSnapshotRepository;
use App\Repositories\Game\GameLegPlayerStatRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\League\LeagueGameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentRepository;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlayerAchievementPublisher
{
    public function __construct(
        private ActivityRecorder $recorder,
        private PlayerBadgeRepository $badges,
        private CareerBadgeReadRepository $careerReads,
        private PlayerGameSnapshotRepository $snapshots,
        private GameLegPlayerStatRepository $legStats,
        private GameRepository $games,
        private PlayoffGameRepository $playoffGames,
        private LeagueGameRepository $leagueGames,
        private TournamentRepository $tournaments,
    ) {}

    public function syncMatch(GameKind $kind, int $gameId): void
    {
        $this->afterCommit(function () use ($kind, $gameId) {
            $this->writeMatch($kind, $gameId);
        });
    }

    public function syncTournament(int $tournamentId): void
    {
        $this->afterCommit(function () use ($tournamentId) {
            $this->writeTournament($tournamentId);
        });
    }

    /**
     * @return array{matches: int, tournaments: int}
     */
    public function backfill(): array
    {
        $matches = 0;
        foreach ($this->careerReads->finishedCompetitiveIds() as $row) {
            $this->syncMatch(GameKind::from($row['kind']), $row['id']);
            $matches++;
        }

        $tournaments = 0;
        foreach ($this->careerReads->finishedTournamentIds() as $tournamentId) {
            $this->syncTournament($tournamentId);
            $tournaments++;
        }

        return ['matches' => $matches, 'tournaments' => $tournaments];
    }

    private function writeMatch(GameKind $kind, int $gameId): void
    {
        $column = $this->legColumn($kind);
        if ($column === null) {
            return;
        }

        $game = $this->findGame($kind, $gameId);
        if ($game === null || ! $this->isFinished($game)) {
            $this->recorder->forgetSubject($kind->value, $gameId);

            return;
        }

        $occurredAt = $this->moment($game->finished_at ?? null);
        $playerIds = array_values(array_unique(array_filter([
            (int) ($game->player1_id ?? 0),
            (int) ($game->player2_id ?? 0),
        ])));

        foreach ($playerIds as $playerId) {
            $this->recorder->playerBadges(
                $playerId,
                $kind->value,
                $gameId,
                $this->badgeUnlocks($playerId, $kind->value, $gameId),
                $occurredAt,
            );
            $this->recorder->playerPersonalBest(
                $playerId,
                $kind->value,
                $gameId,
                $this->records($playerId, $game, $column, $gameId, $occurredAt),
                $occurredAt,
            );
        }
    }

    private function writeTournament(int $tournamentId): void
    {
        if (! $this->careerReads->tournamentIsFinished($tournamentId)) {
            return;
        }

        $tournament = $this->tournaments->findModelOrNull($tournamentId);
        $occurredAt = $this->moment($tournament?->updated_at);
        $source = CareerCatalog::SOURCE_KIND;

        foreach ($this->badges->playerIdsForSource($source, $tournamentId) as $playerId) {
            $amounts = [];
            foreach ($this->badges->amountsForPlayerSource($playerId, $source, $tournamentId) as $row) {
                if ($row['category'] === BadgeCategory::Career->value) {
                    $amounts[$row['key']] = $row['amount'];
                }
            }

            $steps = [];
            foreach ([CareerCatalog::PODIUM, CareerCatalog::APPEARANCES, CareerCatalog::TITLES] as $key) {
                $amount = $amounts[$key] ?? 0;
                if ($amount < 1) {
                    continue;
                }
                $before = $this->badges->hitsBeforeSource($playerId, BadgeCategory::Career->value, $key, $source, $tournamentId);
                $steps[] = [
                    'category' => BadgeCategory::Career->value,
                    'key' => $key,
                    'before' => $before,
                    'after' => $before + $amount,
                ];
            }

            $this->recorder->playerBadges(
                $playerId,
                $source,
                $tournamentId,
                BadgeUnlock::collect($steps),
                $occurredAt,
            );
        }
    }

    /**
     * @return list<array{category: string, key: string, levelName: string, levelLabel: string, title: string}>
     */
    private function badgeUnlocks(int $playerId, string $sourceKind, int $sourceId): array
    {
        $steps = [];
        foreach ($this->badges->amountsForPlayerSource($playerId, $sourceKind, $sourceId) as $row) {
            $before = $this->badges->hitsBeforeSource($playerId, $row['category'], $row['key'], $sourceKind, $sourceId);
            $steps[] = [
                'category' => $row['category'],
                'key' => $row['key'],
                'before' => $before,
                'after' => $before + $row['amount'],
            ];
        }

        $streak = $this->streakStep($playerId, $sourceKind, $sourceId);
        if ($streak !== null) {
            $steps[] = $streak;
        }

        return BadgeUnlock::collect($steps);
    }

    /**
     * @return array{category: string, key: string, before: int, after: int}|null
     */
    private function streakStep(int $playerId, string $kind, int $gameId): ?array
    {
        $wins = [];
        $previousBest = 0;

        foreach ($this->careerReads->streakRows($playerId) as $row) {
            $wins[] = $row['win'];
            $best = WinStreakCalculator::fromWins($wins)['best'];
            if ($row['kind'] === $kind && $row['id'] === $gameId && $best > $previousBest) {
                return [
                    'category' => BadgeCategory::Career->value,
                    'key' => CareerCatalog::WIN_STREAK,
                    'before' => $previousBest,
                    'after' => $best,
                ];
            }
            $previousBest = max($previousBest, $best);
        }

        return null;
    }

    /**
     * @return list<array{kind: string, value: float|int}>
     */
    private function records(int $playerId, Model $game, string $column, int $gameId, Carbon $occurredAt): array
    {
        $snapshot = $this->snapshots->findForSourceable($playerId, $game);
        $average = null;
        if ($snapshot !== null && CareerSnapshotMetrics::isX01((string) $snapshot->game_type)) {
            $raw = is_array($snapshot->metrics) ? ($snapshot->metrics['average'] ?? null) : null;
            $average = is_numeric($raw) ? round((float) $raw, 2) : null;
        }

        $current = $this->legStats->winnerMarksForGame($column, $gameId, $playerId);
        $previous = $this->legStats->winnerMarksBefore($playerId, $occurredAt, $column, $gameId);
        $previousAverage = $average === null
            ? null
            : $this->snapshots->bestAverageExcept(
                $playerId,
                $snapshot?->occurred_at ?? $occurredAt,
                $game->getMorphClass(),
                (int) $game->getKey(),
            );

        return PersonalBestDiff::improved(
            $previousAverage,
            $average,
            $previous['highestHf'],
            $current['highestHf'],
            $previous['fastestQf'],
            $current['fastestQf'],
        );
    }

    private function findGame(GameKind $kind, int $gameId): ?Model
    {
        return match ($kind) {
            GameKind::GROUP => $this->games->findModelOrNull($gameId),
            GameKind::PLAYOFF => $this->playoffGames->findModelOrNull($gameId),
            GameKind::LEAGUE => $this->leagueGames->findModelOrNull($gameId),
            GameKind::QUICK => null,
        };
    }

    private function legColumn(GameKind $kind): ?string
    {
        return match ($kind) {
            GameKind::GROUP => 'game_id',
            GameKind::PLAYOFF => 'playoff_game_id',
            GameKind::LEAGUE => 'league_game_id',
            GameKind::QUICK => null,
        };
    }

    private function isFinished(Model $game): bool
    {
        return $game->status === GameStatus::FINISHED || $game->status === LeagueGameStatus::FINISHED;
    }

    private function moment(mixed $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        return $value ? Carbon::parse($value) : Carbon::now();
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback) {
            try {
                $callback();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
