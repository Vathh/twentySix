<?php

namespace App\Factories;

use App\Domain\PlayoffGameDomain;
use Illuminate\Support\Collection;

class PlayoffBracketFactory
{
    public function createFor16(int $tournamentId, array $playerIds): Collection
    {
        $matches = collect([
            new PlayoffGameDomain(),
        ])
    }
}
