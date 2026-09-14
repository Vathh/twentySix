<?php

namespace App\Enums;

enum InvitationPushType: string
{
    case Friend = 'friend_invitation';
    case Tournament = 'tournament_invitation';
    case Lobby = 'lobby_invitation';
    case League = 'league_game_invitation';
    case Organization = 'organization_invitation';
    case Season = 'season_invitation';
    case LeagueMembership = 'league_invitation';

    public function tab(): string
    {
        return match ($this) {
            self::Friend => 'friends',
            self::Tournament, self::Lobby, self::League, self::Organization, self::Season, self::LeagueMembership => 'gra',
        };
    }
}
