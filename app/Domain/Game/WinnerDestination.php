<?php

namespace App\Domain\Game;

use App\Enums\PlayerSlot;
use App\Enums\PlayoffSlot;

class WinnerDestination
{

    public function __construct(
        public readonly PlayoffSlot $playoffSlot,
        public readonly PlayerSlot $playerSlot,
    )
    {
    }
}
