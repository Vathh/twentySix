<?php

namespace App\Domain\Activity;

enum ActivityType: string
{
    case TournamentChampion = 'tournament.champion';
    case PlayerFirst180 = 'player.first_180';
    case PlayerBadges = 'player.badges';
    case PlayerPersonalBest = 'player.personal_best';
}
