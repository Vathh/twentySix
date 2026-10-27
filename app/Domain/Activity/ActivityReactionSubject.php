<?php

namespace App\Domain\Activity;

enum ActivityReactionSubject: string
{
    case Activity = 'activity';
    case Comment = 'comment';
}
