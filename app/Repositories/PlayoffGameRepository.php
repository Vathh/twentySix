<?php

namespace App\Repositories;

use App\Domain\PlayoffGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\GameStatus;
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
        return PlayoffGameDomain::fromEloquent(PlayoffGame::where('id', $id)->first());
    }

    /**
     * @param int $tournamentId
     * @param PlayoffSlot $slot
     * @return PlayoffGame
     */
    public function findByTournamentIdAndSlot(int $tournamentId, PlayoffSlot $slot): PlayoffGame
    {
        return PlayoffGame::where('tournament_id', $tournamentId)
                            ->where('slot', $slot)
                            ->firstOrFail();
    }
}
