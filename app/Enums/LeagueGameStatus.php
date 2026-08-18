<?php

namespace App\Enums;

enum LeagueGameStatus: string
{
    case SCHEDULED = 'scheduled';
    case FINISHED = 'finished';
    case VOIDED = 'voided';
}
