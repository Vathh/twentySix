<?php

namespace App\Repositories;

use App\Enums\TournamentStatus;
use App\Models\Tournament;

class TournamentRepository
{
    public function create(
        int $seasonId,
        string  $name,
        ?string $date
    ): void
    {
        Tournament::create([
            'season_id' => $seasonId,
            'name' => $name,
            'date' => $date
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function changeStatus(int $tournamentId, TournamentStatus $status): void
    {
        Tournament::where('id', $tournamentId)->update(['status' => $status]);
    }

    public function checkIfTournamentCanBeStarted(int $tournamentId): bool
    {
        $tournament = Tournament::findOrFail($tournamentId);

        return $tournament->status === TournamentStatus::CREATED;
    }
}
