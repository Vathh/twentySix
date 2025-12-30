<?php

namespace App\Enums;

enum PlayoffRound: string{
    case EIGHT = 'EIGHT';
    case QUARTER = 'QUARTER';
    case SEMI = 'SEMI';
    case THIRD = 'THIRD';
    case FINAL = 'FINAL';
}
