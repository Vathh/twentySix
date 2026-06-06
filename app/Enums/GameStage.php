<?php

namespace App\Enums;

enum GameStage: string
{
    case GROUP = 'GROUP';
    case SIXTEEN = 'SIXTEEN';
    case EIGHT = 'EIGHT';
    case QUARTER = 'QUARTER';
    case SEMI = 'SEMI';
    case THIRD = 'THIRD';
    case FINAL = 'FINAL';

    public function label(): string
    {
        return match ($this) {
            self::GROUP => 'Faza grupowa',
            self::SIXTEEN => '1/16 finału',
            self::EIGHT => '1/8 finału',
            self::QUARTER => 'Ćwierćfinał',
            self::SEMI => 'Półfinał',
            self::THIRD => 'Mecz o 3 miejsce',
            self::FINAL => 'Finał',
        };
    }
}
