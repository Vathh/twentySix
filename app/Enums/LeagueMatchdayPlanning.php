<?php

namespace App\Enums;

enum LeagueMatchdayPlanning: string
{
    case FIXED_LENGTH = 'fixed_length';
    case EQUAL_SPAN = 'equal_span';

    public function label(): string
    {
        return match ($this) {
            self::FIXED_LENGTH => 'Długość kolejki + data startu',
            self::EQUAL_SPAN => 'Ramy sezonu (start i koniec)',
        };
    }
}
