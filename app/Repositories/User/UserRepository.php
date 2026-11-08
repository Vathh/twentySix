<?php

namespace App\Repositories\User;

use App\Domain\PlayerDomain;
use App\Models\Users\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function create(string $email, string $password, DateTimeInterface $termsAcceptedAt): User
    {
        return User::create([
            'email' => $email,
            'password' => $password,
            'terms_accepted_at' => $termsAcceptedAt,
        ]);
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
    }

    public function markTermsAccepted(User $user): void
    {
        if ($user->hasAcceptedTerms()) {
            return;
        }

        $user->forceFill([
            'terms_accepted_at' => now(),
        ])->save();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
        ]);
    }

    public function rotateRememberToken(User $user): void
    {
        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function markEmailVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();
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
     *
     * @param  int  $excludeUserId  Użytkownik do wykluczenia z wyników
     * @return Collection<int, array{id: int, email: string, player: PlayerDomain}>
     */
    public function searchByPlayerName(string $searchTerm, int $excludeUserId, int $limit = 20): Collection
    {
        $users = User::with('player')
            ->whereHas('player', function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%'.$searchTerm.'%');
            })
            ->where('id', '!=', $excludeUserId)
            ->limit($limit)
            ->get();

        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'player' => $user->player ? PlayerDomain::fromEloquent($user->player) : null,
                'avatarUrl' => $user->player?->avatarUrl(),
            ];
        });
    }
}
