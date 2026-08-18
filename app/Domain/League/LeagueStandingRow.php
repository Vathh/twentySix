<?php

namespace App\Domain\League;

readonly class LeagueStandingRow
{
    public function __construct(
        public int $playerId,
        public int $played,
        public int $wins,
        public int $losses,
        public int $unitsFor,
        public int $unitsAgainst,
        public int $unitDiff,
        public int $place,
        public bool $needsTiebreak = false,
        public ?string $tieGroupKey = null,
    ) {
    }

    public function withPlace(int $place, bool $needsTiebreak = false, ?string $tieGroupKey = null): self
    {
        return new self(
            playerId: $this->playerId,
            played: $this->played,
            wins: $this->wins,
            losses: $this->losses,
            unitsFor: $this->unitsFor,
            unitsAgainst: $this->unitsAgainst,
            unitDiff: $this->unitDiff,
            place: $place,
            needsTiebreak: $needsTiebreak,
            tieGroupKey: $tieGroupKey,
        );
    }
}
