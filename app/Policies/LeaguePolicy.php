<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeaguePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, League $league): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can_create_leagues == true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, League $league): bool
    {
//        $admins = $league->admins;
//        $test = $user->id;
//        $test2 = $league->admins->contains('id', $test);;
//        return $league->admins->contains('id', $user->id);
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, League $league): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, League $league): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, League $league): bool
    {
        return false;
    }

    public function createSeason(User $user, League $league): bool
    {
        return $league->admins->contains('id', $user->id);
    }
}
