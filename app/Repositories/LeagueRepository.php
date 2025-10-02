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

    public function create(string $name, int $userId): LeagueDomain
    {
        $league = League::create([
            'name' => $name
        ]);

        if(!empty($userId)) {
            $league->admins()->attach($userId);
        }

        return LeagueDomain::fromEloquent($league);
    }
}
