<?php

namespace App\Domain\League;

readonly class LeagueStandingRow
{
    public function __construct(
        public int $playerId,
        public int $played,
        public int $wins,
        public int $draws,
        public int $losses,
        public int $points,
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
            draws: $this->draws,
            losses: $this->losses,
            points: $this->points,
            unitsFor: $this->unitsFor,
            unitsAgainst: $this->unitsAgainst,
            unitDiff: $this->unitDiff,
            place: $place,
            needsTiebreak: $needsTiebreak,
            tieGroupKey: $tieGroupKey,
        );
    }
}
