<?php

namespace App\Repositories\Game;

use App\Domain\Activity\PersonalBestDiff;
use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\Models\Game\GameLegPlayerStat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GameLegPlayerStatRepository
{
    public function createPlaceholder(int $gameLegId, int $playerId, bool $doubleTracked): GameLegPlayerStat
    {
        return GameLegPlayerStat::create([
            'game_leg_id' => $gameLegId,
            'player_id' => $playerId,
            'double_tracked' => $doubleTracked,
        ]);
    }

    public function updateOnLegClose(int $gameLegId, CloseLegPlayerStatsDTO $dto): void
    {
        GameLegPlayerStat::query()
            ->where('game_leg_id', $gameLegId)
            ->where('player_id', $dto->playerId)
            ->update([
                'leg_average' => $dto->legAverage,
                'first_nine_average' => $dto->firstNineAverage,
                'highest_visit' => $dto->highestVisit,
                'highest_finish' => $dto->highestFinish,
                'darts_thrown' => $dto->dartsThrown,
                'checkout_dart' => $dto->checkoutDart,
                'double_attempts' => $dto->doubleAttempts,
                'double_successes' => $dto->doubleSuccesses,
            ]);
    }

    public function resetAfterLegReopen(int $gameLegId): void
    {
        GameLegPlayerStat::query()
            ->where('game_leg_id', $gameLegId)
            ->update([
                'leg_average' => null,
                'first_nine_average' => null,
                'highest_visit' => null,
                'highest_finish' => null,
                'darts_thrown' => null,
                'checkout_dart' => null,
                'double_attempts' => null,
                'double_successes' => null,
            ]);
    }

    /**
     * @return Collection<int, GameLegPlayerStat>
     */
    public function getForLeg(int $gameLegId): Collection
    {
        return GameLegPlayerStat::query()
            ->where('game_leg_id', $gameLegId)
            ->with('player')
            ->get();
    }

    /**
     * @param  list<int>  $gameLegIds
     */
    public function deleteForLegIds(array $gameLegIds): void
    {
        if ($gameLegIds === []) {
            return;
        }

        GameLegPlayerStat::query()->whereIn('game_leg_id', $gameLegIds)->delete();
    }

    /**
     * Lotki zwycięzcy zamkniętego lega. Puste darts_thrown pomijamy.
     *
     * @param  list<int>  $gameLegIds
     * @return list<array{playerId: int, dartsThrown: int}>
     */
    public function winnerDartsForLegs(array $gameLegIds): array
    {
        if ($gameLegIds === []) {
            return [];
        }

        return GameLegPlayerStat::query()
            ->join('game_legs', 'game_legs.id', '=', 'game_leg_player_stats.game_leg_id')
            ->whereIn('game_leg_player_stats.game_leg_id', $gameLegIds)
            ->whereColumn('game_legs.winner_id', 'game_leg_player_stats.player_id')
            ->whereNotNull('game_leg_player_stats.darts_thrown')
            ->orderBy('game_legs.leg_number')
            ->get(['game_leg_player_stats.player_id', 'game_leg_player_stats.darts_thrown'])
            ->map(fn ($row) => [
                'playerId' => (int) $row->player_id,
                'dartsThrown' => (int) $row->darts_thrown,
            ])
            ->all();
    }

    /**
     * @return Collection<int, GameLegPlayerStat>
     */
    public function getForLegIds(array $gameLegIds): Collection
    {
        if ($gameLegIds === []) {
            return collect();
        }

        return GameLegPlayerStat::query()
            ->whereIn('game_leg_id', $gameLegIds)
            ->with('player')
            ->get();
    }

    /**
     * Finish i lotka zwycięzcy w tym meczu.
     *
     * @return array{highestHf: ?int, fastestQf: ?int}
     */
    public function winnerMarksForGame(string $gameColumn, int $gameId, int $playerId): array
    {
        $column = $this->gameColumn($gameColumn);

        return $this->marks($this->winnerLegs($playerId)->where('game_legs.'.$column, $gameId));
    }

    /**
     * Najlepszy finish i lotka ze starszych meczów, bez nóg tego meczu.
     *
     * @return array{highestHf: ?int, fastestQf: ?int}
     */
    public function winnerMarksBefore(int $playerId, CarbonInterface $before, string $gameColumn, int $gameId): array
    {
        $column = $this->gameColumn($gameColumn);
        $query = $this->winnerLegs($playerId)
            ->where('game_legs.finished_at', '<=', $before)
            ->where(function ($legs) use ($column, $gameId) {
                $legs->where('game_legs.'.$column, '!=', $gameId)
                    ->orWhereNull('game_legs.'.$column);
            });

        return $this->marks($query);
    }

    private function gameColumn(string $gameColumn): string
    {
        if (! in_array($gameColumn, ['game_id', 'playoff_game_id', 'league_game_id'], true)) {
            throw new \InvalidArgumentException('Nieznana kolumna meczu.');
        }

        return $gameColumn;
    }

    private function winnerLegs(int $playerId): Builder
    {
        return GameLegPlayerStat::query()
            ->join('game_legs', 'game_legs.id', '=', 'game_leg_player_stats.game_leg_id')
            ->where('game_leg_player_stats.player_id', $playerId)
            ->whereColumn('game_legs.winner_id', 'game_leg_player_stats.player_id')
            ->whereNotNull('game_legs.finished_at')
            ->where(function ($query) {
                $query->whereNotNull('game_legs.game_id')
                    ->orWhereNotNull('game_legs.playoff_game_id')
                    ->orWhereNotNull('game_legs.league_game_id');
            });
    }

    /**
     * @return array{highestHf: ?int, fastestQf: ?int}
     */
    private function marks(Builder $query): array
    {
        $highestHf = (clone $query)
            ->where('game_leg_player_stats.highest_finish', '>=', PersonalBestDiff::HF_MIN)
            ->max('game_leg_player_stats.highest_finish');
        $fastestQf = (clone $query)
            ->whereNotNull('game_leg_player_stats.darts_thrown')
            ->where('game_leg_player_stats.darts_thrown', '<', PersonalBestDiff::QF_UNDER)
            ->min('game_leg_player_stats.darts_thrown');

        return [
            'highestHf' => $highestHf !== null ? (int) $highestHf : null,
            'fastestQf' => $fastestQf !== null ? (int) $fastestQf : null,
        ];
    }
}
