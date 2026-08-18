<?php

namespace App\Policies;

use App\Models\Season\Season;
use App\Models\Users\User;
use Illuminate\Auth\Access\Response;

class SeasonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Season $season): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can_create_organizations == true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Season $season): bool
    {
        return $season->admins->contains('id', $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Season $season): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Season $season): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Season $season): bool
    {
        return false;
    }
}
