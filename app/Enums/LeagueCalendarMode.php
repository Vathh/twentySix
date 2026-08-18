<?php

namespace App\Enums;

enum LeagueCalendarMode: string
{
    case MATCHDAYS = 'matchdays';
    case DEADLINE = 'deadline';

    public function label(): string
    {
        return match ($this) {
            self::MATCHDAYS => 'Kolejki z oknem czasowym',
            self::DEADLINE => 'Pula meczów z jednym terminem',
        };
    }
}
