<?php

namespace App\Enums;

enum LeagueGameStatus: string
{
    case SCHEDULED = 'scheduled';
    case LOBBY = 'lobby';
    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
    case VOIDED = 'voided';
}
