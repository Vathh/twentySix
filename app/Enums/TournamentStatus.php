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

    /**
     * Czy dozwolone jest przejście z bieżącego statusu do $target.
     * Kolejność etapów turnieju jest liniowa i bez cofania: CREATED → GROUP → PLAYOFF → FINISHED.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::CREATED => $target === self::GROUP,
            self::GROUP => $target === self::PLAYOFF,
            self::PLAYOFF => $target === self::FINISHED,
            self::FINISHED => false,
        };
    }
}
