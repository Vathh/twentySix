<?php

namespace App\Repositories\QuickGame;

use App\Domain\Game\QuickGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Models\QuickGame\QuickGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuickGameRepository
{
    /**
     * Tworzy szybki mecz między dwoma graczami
     * @param int $player1Id
     * @param int $player2Id
     * @return int ID utworzonego meczu
     */
    public function create(int $player1Id, int $player2Id, int $legsCount = 2, ?int $lobbyId = null): int
    {
        $quickGame = QuickGame::create([
            'lobby_id' => $lobbyId,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'status' => GameStatus::SCHEDULED,
            'legs_count' => max(1, min(15, $legsCount)),
        ]);

        return $quickGame->id;
    }

    /**
     * Zapisuje wynik szybkiego meczu
     * @param GameResultDTO $dto
     * @return void
     */
    public function finish(GameResultDTO $dto): void
    {
        DB::table('quick_games')
            ->where('id', $dto->gameId)
            ->update([
                'player1_score' => $dto->player1Score,
                'player2_score' => $dto->player2Score,
                'winner_id' => $dto->winnerId,
                'status' => GameStatus::FINISHED
            ]);
    }

    /**
     * Ustawia status meczu na "w trakcie"
     * @param int $gameId
     * @return void
     */
    public function setStatusInProgress(int $gameId): void
    {
        DB::table('quick_games')
            ->where('id', $gameId)
            ->update([
                'status' => GameStatus::IN_PROGRESS
            ]);
    }

    /**
     * Znajduje szybki mecz po ID
     * @param int $id
     * @return QuickGameDomain
     */
    public function find(int $id): QuickGameDomain
    {
        $quickGame = QuickGame::with('player1', 'player2', 'winner')->findOrFail($id);
        return QuickGameDomain::fromEloquent($quickGame, ['player1', 'player2', 'winner']);
    }

    /**
     * Pobiera aktywne szybkie mecze użytkownika (gdzie jest graczem)
     * @param int $userId
     * @return Collection<int, QuickGameDomain>
     */
    public function getActiveForUser(int $userId): Collection
    {
        $quickGames = QuickGame::with(['player1.user', 'player2.user'])
            ->whereHas('player1', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->orWhereHas('player2', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('status', GameStatus::SCHEDULED)
            ->get();

        return $quickGames->map(function ($quickGame) {
            return QuickGameDomain::fromEloquent($quickGame, ['player1', 'player2']);
        });
    }

    /**
     * Tworzy QuickGame i zapisuje wyniki graczy
     * @param array $playerIds Lista ID zarejestrowanych graczy
     * @param int|null $lobbyId ID lobby (opcjonalne)
     * @return int ID utworzonego QuickGame
     */
    public function createWithResults(array $playerIds, ?int $lobbyId = null): int
    {
        // Dla kompatybilności wstecznej - używamy player1_id i player2_id
        $player1Id = $playerIds[0] ?? null;
        $player2Id = $playerIds[1] ?? null;

        $quickGame = QuickGame::create([
            'lobby_id' => $lobbyId,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => null,
            'status' => GameStatus::FINISHED, // Od razu finished, bo wyniki są wysyłane po zakończeniu
        ]);

        return $quickGame->id;
    }

    /**
     * Zapisuje wyniki graczy do quick_game_results
     * @param int $quickGameId
     * @param array $playerResults Array of \App\DTO\QuickGame\PlayerResultDTO
     * @return void
     */
    public function saveResults(int $quickGameId, array $playerResults): void
    {
        $data = array_map(function ($playerResult) use ($quickGameId) {
            return [
                'quick_game_id' => $quickGameId,
                'player_id' => $playerResult->playerId,
                'score' => $playerResult->score,
                'place' => $playerResult->place,
                'average' => $playerResult->average,
                'darts_thrown' => $playerResult->dartsThrown,
                'points_earned' => $playerResult->pointsEarned,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $playerResults);

        if (!empty($data)) {
            \App\Models\QuickGame\QuickGameResult::insert($data);
        }
    }
}












