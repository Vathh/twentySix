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

    /**
     * @param int $id
     * @return LeagueDomain|null
     */
    public function findByIdWithAdmins(int $id): ?LeagueDomain
    {
        $league = League::with('admins')->findOrFail($id);
        return $league ? LeagueDomain::fromEloquent($league, ['admmins']) : null;
    }

    /**
     * @param string $name
     * @param string $description
     * @param int $userId
     * @return LeagueDomain
     */
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
        return League::findOrFail($leagueId)->relatedUsers;
    }

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->admins()->attach($userId);
    }

    public function removeAdmin(int $leagueId, int $userId): void
    {
        $league = League::findOrFail($leagueId);
        $league->admins()->detach($userId);
    }

    public function update(int $leagueId, string $name, string $description): void
    {
        League::where('id', $leagueId)->update([
            'name' => $name,
            'description' => $description
        ]);
    }
}
