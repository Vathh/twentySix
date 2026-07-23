<?php

namespace App\Enums;

enum InvitationPushType: string
{
    case Friend = 'friend_invitation';
    case Tournament = 'tournament_invitation';
    case Lobby = 'lobby_invitation';

    public function tab(): string
    {
        return match ($this) {
            self::Friend => 'friends',
            self::Tournament => 'tournament',
            self::Lobby => 'pojedynek',
        };
    }
}
