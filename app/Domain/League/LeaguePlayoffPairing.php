<?php

namespace App\Domain\League;

readonly class LeaguePlayoffPairing
{
    public function __construct(
        public int $higherDivisionId,
        public int $lowerDivisionId,
        public int $higherPlayerId,
        public int $lowerPlayerId,
    ) {
    }
}
