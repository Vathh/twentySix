<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case CREATED = 'created';
    case GROUP = 'group';
    case PLAYOFF = 'playoff';
    case FINISHED = 'finished';
}
