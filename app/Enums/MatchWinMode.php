<?php

namespace App\Enums;

enum MatchWinMode: string
{
    case FIRST_TO = 'first_to';
    case BEST_OF = 'best_of';

    public function label(): string
    {
        return match ($this) {
            self::FIRST_TO => 'First to',
            self::BEST_OF => 'Best of',
        };
    }
}
