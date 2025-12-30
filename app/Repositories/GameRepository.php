<?php

namespace App\Repositories;

use App\Domain\GameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameRepository
{
    /**
     * @throws \Throwable
     */
    public function createGames(array $games): void
    {
        DB::table('games')->insert($games);
    }

    public function update(GameResultDTO $dto): void
    {
        DB::table('games')
            ->where('id', $dto->gameId)
            ->update([
                'player1_score' => $dto->player1Score,
                'player2_score' => $dto->player2Score,
                'winner_id' => $dto->winnerId,
                'status' => GameStatus::FINISHED
            ]);
    }

    public function setStatusInProgress(int $gameId): void
    {
        DB::table('games')
            ->where('id', $gameId)
            ->update([
                'status' => GameStatus::IN_PROGRESS
            ]);
    }

    /**
     * @param int $tournamentId
     * @param int $groupNumber
     * @return Collection<int, GameDomain>
     */
    public function getFinishedGroupGames(int $tournamentId, int $groupNumber): Collection
    {
        return Game::where('tournament_id', $tournamentId)
                    ->where('group_number', $groupNumber)
                    ->where('status', GameStatus::FINISHED)
                    ->get()
                    ->map(fn($game) => GameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));
    }

    /**
     * @param int $tournamentId
     * @return Collection<int, GameDomain>
     */
    public function getActiveGames(int $tournamentId): Collection
    {
        return Game::where('tournament_id', $tournamentId)
                    ->where('status', GameStatus::SCHEDULED)
                    ->get()
                    ->map(fn($game) => GameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']));
    }

    public function checkIfPlayoffShouldBeStarted(int $tournamentId): bool
    {
        return Game::where('tournament_id', $tournamentId)
                    ->where('status', GameStatus::SCHEDULED)
                    ->get()
                    ->count() === 0;
    }
}
