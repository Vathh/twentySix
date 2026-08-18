<?php

namespace App\Enums;

enum LeagueGamePurpose: string
{
    case REGULAR = 'regular';
    case PROMOTION_PLAYOFF = 'promotion_playoff';
    case TIEBREAKER = 'tiebreaker';
}
