<?php

namespace App\Repositories;

use App\Domain\LeagueDomain;
use App\Models\League;
use Illuminate\Support\Collection;

class LeagueRepository
{
    /**
     * @return Collection<int, LeagueDomain>
     */
    public function getAll(): Collection
    {
        return League::all()->map(fn($league) => LeagueDomain::fromEloquent($league));
    }

    public function findByIdWithAdmins(int $id): ?LeagueDomain
    {
        $league = League::with('admins')->find($id);
        return $league ? LeagueDomain::fromEloquentWithAdmins($league) : null;
    }

    public function create(string $name, string $description, int $userId): LeagueDomain
    {
        $league = League::create([
            'name' => $name,
            'description' => $description,
        ]);

        if(!empty($userId)) {
            $league->admins()->attach($userId);
        }

        return LeagueDomain::fromEloquent($league);
    }

    public function getRelatedUsers(int $leagueId): Collection
    {
        return League::findOrFail($$leagueId)->relatedUsers->get();
    }

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($$leagueId);
        $league->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($$leagueId);
        $league->relatedUsers()->detach($userId);
    }
}
