<?php

namespace App\Enums;

enum AssignableEntityType: string
{
    case ORGANIZATION = 'organization';
    case SEASON = 'season';
    case LEAGUE = 'league';
}
