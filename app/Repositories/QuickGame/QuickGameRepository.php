<?php

namespace App\Repositories\QuickGame;

use App\Enums\GameStatus;
use App\Models\QuickGame\QuickGame;
use Illuminate\Support\Collection;

class QuickGameRepository
{
    /**
     * Tworzy QuickGame po zakończeniu sesji FFA (player1/player2 dla schematu).
     *
     * @param  list<int>  $playerIds
     */
    public function createWithResults(array $playerIds, ?int $lobbyId = null): int
    {
        $player1Id = $playerIds[0] ?? null;
        $player2Id = $playerIds[1] ?? null;

        $quickGame = QuickGame::create([
            'lobby_id' => $lobbyId,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'status' => GameStatus::FINISHED,
        ]);

        return $quickGame->id;
    }

    /**
     * @param  array<\App\DTO\QuickGame\PlayerResultDTO>  $playerResults
     */
    public function saveResults(int $quickGameId, array $playerResults): void
    {
        $rows = array_map(function ($playerResult) use ($quickGameId) {
            return [
                'quick_game_id' => $quickGameId,
                'player_id' => $playerResult->playerId,
                'score' => $playerResult->score,
                'place' => $playerResult->place ?? 0,
                'average' => $playerResult->average,
                'darts_thrown' => $playerResult->dartsThrown,
                'points_earned' => $playerResult->pointsEarned,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $playerResults);

        if ($rows === []) {
            return;
        }

        \App\Models\QuickGame\QuickGameResult::insert($rows);
    }

    /**
     * Surowy model Eloquent (np. do serwisów scoringu operujących na QuickGame).
     *
     * @param  string[]  $relations
     */
    public function findModel(int $gameId, array $relations = []): QuickGame
    {
        return QuickGame::with($relations)->findOrFail($gameId);
    }

    /**
     * @return Collection<int, QuickGame>
     */
    public function findInProgressForPlayer(int $playerId): Collection
    {
        return QuickGame::query()
            ->with(['player1:id,name', 'player2:id,name'])
            ->where('status', GameStatus::IN_PROGRESS)
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->get();
    }

    /**
     * Aktualizuje kolumny wyniku QuickGame (np. po zakończeniu sesji FFA).
     *
     * @param  array<string, mixed>  $fields
     */
    public function updateResultFields(int $quickGameId, array $fields): void
    {
        QuickGame::where('id', $quickGameId)->update($fields);
    }
}
