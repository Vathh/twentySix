<?php

namespace App\Repositories\Tournament;

use App\Domain\Tournament\TournamentResultDomain;
use App\Models\TournamentResult;
use Illuminate\Support\Collection;

class TournamentResultRepository
{
    public function createMany(array $tournamentResults): void
    {
        $mapped = array_map(fn (TournamentResultDomain $result) => [
            'season_id' => $result->seasonId,
            'tournament_id' => $result->tournamentId,
            'player_id' => $result->playerId,
            'points' => $result->points,
            'place' => $result->place,
            'elimination_stage' => $result->eliminationStage?->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], $tournamentResults);

        TournamentResult::insert($mapped);
    }

    public function create(TournamentResultDomain $tournamentResult): void
    {
        $this->createMany([$tournamentResult]);
    }
}











