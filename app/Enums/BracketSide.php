<?php

namespace App\Enums;

enum BracketSide: string
{
    case Main = 'main';
    case Winners = 'winners';
    case Losers = 'losers';
    case GrandFinal = 'grand_final';
    case Consolation = 'consolation';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Drabinka',
            self::Winners => 'Wygrani',
            self::Losers => 'Przegrani',
            self::GrandFinal => 'Grand Final',
            self::Consolation => 'Drabinka pocieszenia',
        };
    }

    public function sectionTitle(): string
    {
        return match ($this) {
            self::Main => 'Drabinka główna',
            self::Consolation => 'Drabinka pocieszenia',
            self::Winners => 'Drabinka wygranych',
            self::Losers => 'Drabinka przegranych',
            self::GrandFinal => 'Grand Final',
        };
    }

    public function isConsolation(): bool
    {
        return $this === self::Consolation;
    }
}
