<?php

namespace App\Domain\Activity;

enum ActivityChannel: string
{
    case Friends = 'friends';
    case Organization = 'organization';
}
