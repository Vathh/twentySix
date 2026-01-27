<?php

namespace App\Repositories;

use App\DTO\MatchLegDTO;
use App\Models\MatchLeg;

class MatchLegRepository
{
    /**
     * @param MatchLegDTO[] $legs
     * @param int|null $gameId
     * @param int|null $playoffGameId
     * @param int|null $quickGameId
     * @return void
     */
    public function createMany(array $legs, ?int $gameId = null, ?int $playoffGameId = null, ?int $quickGameId = null): void
    {
        $data = array_map(function (MatchLegDTO $leg) use ($gameId, $playoffGameId, $quickGameId) {
            return [
                'game_id' => $gameId,
                'playoff_game_id' => $playoffGameId,
                'quick_game_id' => $quickGameId,
                'leg_number' => $leg->legNumber,
                'player1_score' => $leg->player1Score,
                'player2_score' => $leg->player2Score,
                'winner_id' => $leg->winnerId,
                'player1_average' => $leg->player1Average,
                'player2_average' => $leg->player2Average,
                'player1_darts_thrown' => $leg->player1DartsThrown,
                'player2_darts_thrown' => $leg->player2DartsThrown,
                'checkout_score' => $leg->checkoutScore,
                'started_at' => $leg->startedAt,
                'finished_at' => $leg->finishedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $legs);

        if (!empty($data)) {
            MatchLeg::insert($data);
        }
    }

    /**
     * @param int $gameId
     * @return \Illuminate\Support\Collection
     */
    public function getByGameId(int $gameId): \Illuminate\Support\Collection
    {
        return MatchLeg::where('game_id', $gameId)
            ->orderBy('leg_number')
            ->get();
    }

    /**
     * @param int $playoffGameId
     * @return \Illuminate\Support\Collection
     */
    public function getByPlayoffGameId(int $playoffGameId): \Illuminate\Support\Collection
    {
        return MatchLeg::where('playoff_game_id', $playoffGameId)
            ->orderBy('leg_number')
            ->get();
    }
}
