<?php

namespace App\Repositories\Game;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\Models\Game\GameLegPlayerStat;
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
                'double_tracked' => $dto->doubleTracked,
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
}
