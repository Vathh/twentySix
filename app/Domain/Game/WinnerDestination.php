<?php

namespace App\Domain\Game;

use App\Enums\PlayerSlot;
use InvalidArgumentException;

class WinnerDestination
{
    public function __construct(
        public readonly string $playoffSlot,
        public readonly PlayerSlot $playerSlot,
    ) {
    }

    public static function parse(string $destinationSlot): self
    {
        if (! preg_match('/^(.*)-([AB])$/', $destinationSlot, $matches)) {
            throw new InvalidArgumentException(
                "Nieprawidłowy slot docelowy zwycięzcy: {$destinationSlot}.",
            );
        }

        return new self($matches[1], PlayerSlot::from($matches[2]));
    }
}
