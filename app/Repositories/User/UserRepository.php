<?php

namespace App\Repositories\User;

use App\Domain\PlayerDomain;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository
{
    /**
     * Wyszukuje użytkowników po nazwie gracza (dla wyszukiwania znajomych)
     * @param string $searchTerm
     * @param int $excludeUserId Użytkownik do wykluczenia z wyników
     * @param int $limit
     * @return Collection<int, array{id: int, email: string, player: PlayerDomain}>
     */
    public function searchByPlayerName(string $searchTerm, int $excludeUserId, int $limit = 20): Collection
    {
        $users = User::with('player')
            ->whereHas('player', function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%');
            })
            ->where('id', '!=', $excludeUserId)
            ->limit($limit)
            ->get();

        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'player' => $user->player ? PlayerDomain::fromEloquent($user->player) : null,
            ];
        });
    }
}











