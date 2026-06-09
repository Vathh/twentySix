<?php

namespace App\Enums;

enum GameKind: string
{
    case GROUP = 'group';
    case PLAYOFF = 'playoff';
    case QUICK = 'quick';
}
