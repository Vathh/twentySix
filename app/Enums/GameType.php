<?php

namespace App\Enums;

enum GameType: string
{
    case GROUP = 'group';
    case PLAYOFF = 'playoff';
    case QUICK_MATCH = 'quick_match';
}
