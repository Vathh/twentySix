<?php

namespace App\Policies;

use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Users\User;

class LeaguePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, League $league): bool
    {
        return true;
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->admins->contains('id', $user->id);
    }

    public function update(User $user, League $league): bool
    {
        return $league->organization->admins->contains('id', $user->id);
    }

    public function delete(User $user, League $league): bool
    {
        return false;
    }
}
