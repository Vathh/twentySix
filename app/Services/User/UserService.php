<?php

namespace App\Services\User;

use App\Models\Users\User;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UserService
{

    public function __construct(
        private UserRepository $userRepository
    )
    {
    }

    public function search(Collection|array $relatedUsers, ?string $search): Collection
    {
        if($search === null || trim($search) === '') {
            return collect();
        }

        if (strlen($search) < 5) {
            throw ValidationException::withMessages([
                'search' => 'Wpisz co najmniej 5 znaków, aby wyszukać użytkowników.'
            ]);
        }

        $relatedUsersIds = collect($relatedUsers)->pluck('id');

        return User::whereHas('player', function ($query) use ($search) {
            $query->where('name', 'LIKE', "%$search%");
            })
            ->with('player')
            ->get()
            ->sortBy('player.name')
            ->reject(fn ($user) => $relatedUsersIds->contains($user->id));
    }

    public function sortByName(array $relatedUsers): array
    {
        if(count($relatedUsers) > 0){
            usort($relatedUsers, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        return $relatedUsers;
    }

    public function sortByNameAndRejectAdmins(array $relatedUsers, array $admins): Collection
    {
        $adminsIds = collect($admins)->pluck('id');

        if(count($relatedUsers) > 0){
            return collect($relatedUsers)
                    ->sortBy('name')
                    ->reject(fn ($user) => $adminsIds->contains($user['id']))
                    ->map(fn($user) => (object) $user)
                    ->values();
        }else {
            return collect();
        }
    }

    /**
     * Wyszukuje użytkowników po nazwie gracza (dla API)
     * @param string $searchTerm
     * @param int $excludeUserId
     * @param int $limit
     * @return Collection
     */
    public function searchByPlayerName(string $searchTerm, int $excludeUserId, int $limit = 20): Collection
    {
        return $this->userRepository->searchByPlayerName($searchTerm, $excludeUserId, $limit);
    }

    /**
     * Wyszukiwarka użytkowników do zaproszeń turniejowych (web).
     *
     * @param  Collection<int, int>  $excludeUserIds
     */
    public function searchForTournamentInvitations(string $search, Collection $excludeUserIds): Collection
    {
        if (trim($search) === '') {
            return collect();
        }

        if (strlen($search) < 5) {
            throw ValidationException::withMessages([
                'search' => 'Wpisz co najmniej 5 znaków, aby wyszukać użytkowników.',
            ]);
        }

        return User::whereHas('player', function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
            ->with('player')
            ->get()
            ->sortBy('player.name')
            ->reject(fn ($user) => $excludeUserIds->contains($user->id))
            ->values();
    }
}












