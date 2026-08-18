<?php

namespace App\Domain\League;

readonly class LeagueDivisionSnapshot
{
    public function __construct(
        public int $id,
        public int $position,
        public string $name,
        public int $capacity,
        public int $promoteDirect,
        public int $promotePlayoff,
        /** @var list<int> */
        public array $playerIds,
    ) {
    }
}
