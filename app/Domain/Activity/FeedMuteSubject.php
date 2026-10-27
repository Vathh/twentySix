<?php

namespace App\Domain\Activity;

enum FeedMuteSubject: string
{
    case Organization = 'organization';
    case User = 'user';
}
