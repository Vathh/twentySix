<?php

namespace App\Services;

use App\Domain\LeagueDomain;
use App\Models\League;
use App\Repositories\LeagueRepository;
use Illuminate\Support\Collection;

class LeagueService
{
    public function __construct(private LeagueRepository $leagueRepository)
    {
    }

    /**
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->leagueRepository
                    ->getAll()
                    ->sortByDesc(fn(LeagueDomain $league) => $league->updatedAt)
                    ->values();
    }

    public function getByIdWithAdmins(int $id): ?LeagueDomain
    {
        return $this->leagueRepository->findByIdWithAdmins($id);
    }

    public function create(string $name, string $description, int $userId): LeagueDomain
    {
        return $this->leagueRepository->create($name, $description, $userId);
    }
}
