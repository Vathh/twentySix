<?php

namespace App\Policies;

use App\Models\League\LeagueSeason;
use App\Models\Users\User;

class LeagueSeasonPolicy
{
    public function view(?User $user, LeagueSeason $leagueSeason): bool
    {
        return true;
    }

    public function update(User $user, LeagueSeason $leagueSeason): bool
    {
        return $leagueSeason->league->organization->admins->contains('id', $user->id);
    }

    public function delete(User $user, LeagueSeason $leagueSeason): bool
    {
        $leagueSeason->loadMissing('league.organization.admins');

        return $leagueSeason->league->organization->admins->contains('id', $user->id);
    }
}
