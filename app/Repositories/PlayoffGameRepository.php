<?php

namespace App\Repositories;

use App\Domain\Game\PlayoffGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
use App\Enums\PlayerSlot;
use App\Enums\PlayoffSlot;
use App\Models\PlayoffGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayoffGameRepository
{
    /**
     * @param Collection<PlayoffGameDomain> $games
     * @return void
     */
    public function createMany(Collection $games): void
    {
        foreach ($games as $game) {
            PlayoffGame::create([
                'tournament_id' => $game->tournamentId,
                'round' => $game->round,
                'slot' => $game->slot,
                'player1_id' => $game->player1Id ?: null,
                'player2_id' => $game->player2Id ?: null,
                'winner_destination_slot' => $game->winnerDestinationSlot ?: null,
            ]);
        }
    }

    public function finish(GameResultDTO $dto): void
    {
        DB::table('playoff_games')
            ->where('id', $dto->gameId)
            ->update([
                'player1_score' => $dto->player1Score,
                'player2_score' => $dto->player2Score,
                'winner_id' => $dto->winnerId,
                'status' => GameStatus::FINISHED
            ]);
    }

    /**
     * @param int $tournamentId
     * @return Collection<PlayoffGameDomain>
     */
    public function getActive(int $tournamentId): Collection
    {
        return PlayoffGame::with(['tournament', 'player1', 'player2'])
                            ->where('tournament_id', $tournamentId)
                            ->where('status', GameStatus::SCHEDULED)
                            ->get()
                            ->map(fn($game) => PlayoffGameDomain::fromEloquent($game, ['tournament', 'player1', 'player2']));
    }

    public function find(int $id): ?PlayoffGameDomain
    {
        $test1 = PlayoffGame::findOrFail($id);

        $test = PlayoffGameDomain::fromEloquent($test1);


        return PlayoffGameDomain::fromEloquent(PlayoffGame::where('id', $id)->firstOrFail());
    }

    public function setPlayer1Slot(int $tournamentId, PlayoffSlot $slot, int $playerId): void
    {
        PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->update(['player1_id' => $playerId]);
    }

    public function setPlayer2Slot(int $tournamentId, PlayoffSlot $slot, int $playerId): void
    {
        PlayoffGame::where('tournament_id', $tournamentId)
            ->where('slot', $slot)
            ->update(['player2_id' => $playerId]);
    }
}
