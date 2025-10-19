<?php

namespace App\Services;

use App\Domain\LeagueDomain;
use App\Models\League;
use App\Models\User;
use App\Repositories\LeagueRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    public function addRelatedUser(int $leagueId, int $userId): void
    {
        $this->leagueRepository->addRelatedUser($leagueId, $userId);
    }

    public function removeRelatedUser(int $leagueId, int $userId): void
    {
        $this->leagueRepository->removeRelatedUser($leagueId, $userId);
    }

    public function addAdmin(int $leagueId, int $userId): void
    {
        $this->leagueRepository->addAdmin($leagueId, $userId);
    }

    public function removeAdmin(int $leagueId, int $userId): void
    {
        $this->leagueRepository->removeAdmin($leagueId, $userId);
    }

    public function update(int $leagueId, string $name, string $description): void
    {
        $this->leagueRepository->update($leagueId, $name, $description);
    }

    public function searchUsers(LeagueDomain $league, ?string $search): Collection
    {
        if($search === null || trim($search) === '') {
            return collect();
        }

        if (strlen($search) < 5) {
            throw ValidationException::withMessages([
                'search' => 'Wpisz co najmniej 5 znaków, aby wyszukać użytkowników.'
            ]);
        }

        $relatedUsersIds = collect($league->relatedUsers)->pluck('id');

        return User::whereHas('player', function ($query) use ($search) {
                                        $query->where('name', 'LIKE', "%$search%");
                                        })
                    ->with('player')
                    ->get()
                    ->sortBy('player.name')
                    ->reject(fn ($user) => $relatedUsersIds->contains($user->id));
    }

    public function getRelatedUsersSortedByName(LeagueDomain $league): array
    {
        $relatedUsers = $league->relatedUsers;

        if(count($relatedUsers) > 0){
            usort($relatedUsers, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        return $relatedUsers;
    }

    public function getRelatedUsersSortedByNameAndRejectAdmins(LeagueDomain $league): Collection
    {
        $relatedUsers = $league->relatedUsers;
        $admins = $league->admins;
        $adminsIds = collect($admins)->pluck('id');

        if(count($relatedUsers) > 0){
            $relatedUsers = collect($relatedUsers)
                ->sortBy('name')
                ->reject(fn ($user) => $adminsIds->contains($user['id']))
                ->map(fn($user) => (object) $user);
        }

        return $relatedUsers;
    }
}
