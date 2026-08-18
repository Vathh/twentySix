<?php

namespace App\Policies;

use App\Models\League\LeagueGame;
use App\Models\Users\User;

class LeagueGamePolicy
{
    public function view(?User $user, LeagueGame $leagueGame): bool
    {
        return true;
    }

    public function update(User $user, LeagueGame $leagueGame): bool
    {
        return $leagueGame->season->league->organization->admins->contains('id', $user->id);
    }
}
