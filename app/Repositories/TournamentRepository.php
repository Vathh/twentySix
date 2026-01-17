<?php

namespace App\Repositories;

use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\TournamentStatus;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Throwable;

class TournamentRepository
{
    /**
     * @return Collection<int, TournamentDomain>
     */
    public function getAll(): Collection
    {
        return Tournament::all()->map(fn($tournament) => TournamentDomain::fromEloquent($tournament));
    }

    public function create(
        int     $seasonId,
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
     * @throws Throwable
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


    /**
     * @param int $tournamentId
     * @return TournamentDomain|null
     */
    public function findByIdWithSeasonAndPointScheme(int $tournamentId): ?TournamentDomain
    {
        $tournament = Tournament::with(['season', 'pointScheme.rules'])->findOrFail($tournamentId);

        return TournamentDomain::fromEloquent($tournament, ['season', 'pointScheme', 'pointScheme.rules']);
    }
}
