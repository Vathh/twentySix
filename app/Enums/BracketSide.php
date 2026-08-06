<?php

namespace App\Enums;

enum BracketSide: string
{
    case Main = 'main';
    case Winners = 'winners';
    case Losers = 'losers';
    case GrandFinal = 'grand_final';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Drabinka',
            self::Winners => 'Wygrani',
            self::Losers => 'Przegrani',
            self::GrandFinal => 'Grand Final',
        };
    }
}
