<?php

namespace App\Enums;

enum WebcamMatchStatus: string
{
    case Lobby = 'lobby';
    case Live = 'live';
    case Finished = 'finished';
}
