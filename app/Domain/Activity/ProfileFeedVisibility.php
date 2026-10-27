<?php

namespace App\Domain\Activity;

enum ProfileFeedVisibility: string
{
    case Public = 'public';
    case Friends = 'friends';
    case FriendsAndCircle = 'friends_and_circle';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Wszyscy',
            self::Friends => 'Tylko znajomi',
            self::FriendsAndCircle => 'Znajomi i gracze ze wspólnych rozgrywek',
        };
    }
}
