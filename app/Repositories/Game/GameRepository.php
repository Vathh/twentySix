<?php

namespace App\Repositories\Game;

use App\Domain\Game\GroupGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Models\Game\Game;
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

    public function finish(GameResultDTO $dto): void
    {
        DB::table('games')
            ->where('id', $dto->gameId)
            ->update([
                'player1_score' => $dto->player1Score,
                'player2_score' => $dto->player2Score,
                'player1_legs_in_set' => 0,
                'player2_legs_in_set' => 0,
                'current_set_number' => 1,
                'winner_id' => $dto->winnerId,
                'status' => GameStatus::FINISHED,
            ]);
    }

    public function tryLockScheduled(int $gameId): bool
    {
        return DB::table('games')
            ->where('id', $gameId)
            ->where('status', GameStatus::SCHEDULED)
            ->update(['status' => GameStatus::IN_PROGRESS]) === 1;
    }

    public function resetToScheduled(Game $game): void
    {
        $game->status = GameStatus::SCHEDULED;
        $game->player1_score = 0;
        $game->player2_score = 0;
        $game->player1_legs_in_set = 0;
        $game->player2_legs_in_set = 0;
        $game->current_set_number = 1;
        $game->winner_id = null;
        $this->save($game);
    }

    public function tryUnlockInProgress(int $gameId): bool
    {
        return DB::table('games')
            ->where('id', $gameId)
            ->where('status', GameStatus::IN_PROGRESS)
            ->update(['status' => GameStatus::SCHEDULED]) === 1;
    }

    public function isInProgress(int $gameId): bool
    {
        return Game::query()
            ->where('id', $gameId)
            ->where('status', GameStatus::IN_PROGRESS)
            ->exists();
    }

    /**
     * @return Collection<int, GroupGameDomain>
     */
    public function getFinishedGroupGames(int $tournamentId, int $groupNumber): Collection
    {
        return Game::with(['player1', 'player2', 'winner'])
            ->where('tournament_id', $tournamentId)
            ->where('group_number', $groupNumber)
            ->where('status', GameStatus::FINISHED)
            ->get()
            ->map(fn ($game) => GroupGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));
    }

    /**
     * @return Collection<int, GroupGameDomain>
     */
    public function getActive(int $tournamentId): Collection
    {
        return Game::with(['tournament', 'player1', 'player2'])
            ->where('tournament_id', $tournamentId)
            ->whereIn('status', [GameStatus::SCHEDULED, GameStatus::IN_PROGRESS])
            ->get()
            ->map(fn ($game) => GroupGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']));
    }

    /**
     * Wszystkie mecze grupowe turnieju (także finished) — macierz sędziego.
     *
     * @return Collection<int, GroupGameDomain>
     */
    public function getAllWithPlayers(int $tournamentId): Collection
    {
        return Game::with(['tournament', 'player1', 'player2', 'winner', 'referee'])
            ->where('tournament_id', $tournamentId)
            ->get()
            ->map(fn ($game) => GroupGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2', 'winner', 'referee']));
    }

    public function checkIfPlayoffShouldBeStarted(int $tournamentId): bool
    {
        $groupGames = Game::query()->where('tournament_id', $tournamentId);

        if (! (clone $groupGames)->exists()) {
            return false;
        }

        return ! (clone $groupGames)
            ->where('status', '!=', GameStatus::FINISHED)
            ->exists();
    }

    public function find(int $id): ?GroupGameDomain
    {
        $game = Game::with('player1', 'player2', 'winner')->where('id', $id)->firstOrFail();

        return GroupGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']);
    }

    /**
     * Surowy model Eloquent (np. do serwisów lock/scoring/korekty operujących na Game).
     *
     * @param  string[]  $relations
     */
    public function findModel(int $gameId, array $relations = []): Game
    {
        return Game::with($relations)->findOrFail($gameId);
    }

    /**
     * @return Collection<int, Game>
     */
    public function findInProgressForPlayer(int $playerId): Collection
    {
        return Game::query()
            ->with(['player1:id,name', 'player2:id,name', 'tournament:id,name'])
            ->where('status', GameStatus::IN_PROGRESS)
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->get();
    }

    /**
     * Surowe modele wszystkich meczów grupowych turnieju (np. do payloadu live matrix).
     *
     * @return Collection<int, Game>
     */
    public function getAllForTournament(int $tournamentId, array $columns = ['*']): Collection
    {
        return Game::query()
            ->where('tournament_id', $tournamentId)
            ->get($columns);
    }

    public function findModelOrNull(int $id): ?Game
    {
        return Game::query()->find($id);
    }

    /**
     * Zapisuje zmiany na modelu Game (np. po mutacjach stanu scoringu w Service).
     */
    public function save(Game $game): void
    {
        $game->save();
    }

    /**
     * @return Collection<int, Game>
     */
    public function listFinished(): Collection
    {
        return Game::query()
            ->where('status', GameStatus::FINISHED)
            ->orderBy('id')
            ->get();
    }
}
