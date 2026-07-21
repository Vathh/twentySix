<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case CREATED = 'created';
    case GROUP = 'group';
    case PLAYOFF = 'playoff';
    case FINISHED = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Zaplanowany',
            self::GROUP => 'Grupowa',
            self::PLAYOFF => 'Playoff',
            self::FINISHED => 'Zakończony',
        };
    }

    /** Wariant badge UI: planned | live | finished */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::CREATED => 'planned',
            self::GROUP, self::PLAYOFF => 'live',
            self::FINISHED => 'finished',
        };
    }
}
