<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case CREATED = 'created';
    case STARTED = 'started';
    case FINISHED = 'finished';
}
