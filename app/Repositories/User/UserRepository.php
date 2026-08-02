<?php

namespace App\Repositories\User;

use App\Domain\PlayerDomain;
use App\Models\Users\User;
use Illuminate\Support\Collection;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findModel(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(string $email, string $password): User
    {
        return User::create([
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
        ]);
    }

    /**
     * Wyszukuje użytkowników po nazwie gracza (Eloquent User z relacją `player`), posortowanych po nazwie gracza.
     *
     * @return Collection<int, User>
     */
    public function findByPlayerNameLike(string $search): Collection
    {
        return User::whereHas('player', function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
            ->with('player')
            ->get()
            ->sortBy('player.name');
    }

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












