<?php

namespace App\Enums;

enum GrandFinalMode: string
{
    case Single = 'single';
    case Reset = 'reset';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Jeden mecz o tytuł',
            self::Reset => 'Reset (do dwóch meczów, gdy wygra LB)',
        };
    }
}
