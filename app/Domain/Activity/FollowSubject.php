<?php

namespace App\Domain\Activity;

enum FollowSubject: string
{
    case Organization = 'organization';
    case User = 'user';
}
