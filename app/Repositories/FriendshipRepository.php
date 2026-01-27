<?php

namespace App\Repositories;

use App\Domain\FriendshipDomain;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FriendshipRepository
{
    /**
     * Dodaje znajomość między dwoma użytkownikami (symetryczna relacja)
     * @param int $userId
     * @param int $friendId
     * @return void
     */
    public function addFriendship(int $userId, int $friendId): void
    {
        // Zapobieganie dodaniu siebie jako znajomego
        if ($userId === $friendId) {
            return;
        }

        // Sprawdź czy relacja już istnieje (w którąkolwiek stronę)
        $exists = DB::table('friendships')
            ->where(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $userId)
                      ->where('friend_id', $friendId);
            })
            ->orWhere(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $friendId)
                      ->where('friend_id', $userId);
            })
            ->exists();

        if (!$exists) {
            // Dodaj relację w jedną stronę (A -> B)
            DB::table('friendships')->insert([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Usuwa znajomość między dwoma użytkownikami
     * @param int $userId
     * @param int $friendId
     * @return void
     */
    public function removeFriendship(int $userId, int $friendId): void
    {
        DB::table('friendships')
            ->where(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $userId)
                      ->where('friend_id', $friendId);
            })
            ->orWhere(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $friendId)
                      ->where('friend_id', $userId);
            })
            ->delete();
    }

    /**
     * Pobiera listę znajomych użytkownika
     * @param int $userId
     * @return Collection<int, FriendshipDomain>
     */
    public function getFriends(int $userId): Collection
    {
        // Pobierz znajomych gdzie user_id = userId (znajomi użytkownika)
        $friendshipsAsUser = DB::table('friendships')
            ->where('user_id', $userId)
            ->pluck('friend_id');

        // Pobierz znajomych gdzie friend_id = userId (użytkownik jest znajomym)
        $friendshipsAsFriend = DB::table('friendships')
            ->where('friend_id', $userId)
            ->pluck('user_id');

        // Połącz oba zbiory
        $friendIds = $friendshipsAsUser->merge($friendshipsAsFriend)->unique();

        if ($friendIds->isEmpty()) {
            return collect();
        }

        // Pobierz użytkowników z ich graczami
        $friends = User::with('player')
            ->whereIn('id', $friendIds)
            ->get();

        // Pobierz ID relacji dla mapowania
        $friendshipIds = DB::table('friendships')
            ->where(function ($query) use ($userId, $friendIds) {
                $query->where('user_id', $userId)
                      ->whereIn('friend_id', $friendIds);
            })
            ->orWhere(function ($query) use ($userId, $friendIds) {
                $query->where('friend_id', $userId)
                      ->whereIn('user_id', $friendIds);
            })
            ->get()
            ->keyBy(function ($friendship) use ($userId) {
                // Zwróć friend_id jako klucz
                return $friendship->user_id === $userId 
                    ? $friendship->friend_id 
                    : $friendship->user_id;
            });

        return $friends->map(function ($friend) use ($friendshipIds, $userId) {
            $friendshipRecord = $friendshipIds->get($friend->id);
            $friendshipId = $friendshipRecord ? $friendshipRecord->id : 0;
            
            // Tylko użytkownicy z graczem mogą być znajomymi
            if (!$friend->player) {
                return null;
            }
            
            $friendPlayer = \App\Domain\PlayerDomain::fromEloquent($friend->player);
            
            return FriendshipDomain::fromData(
                $friendshipId,
                $userId,
                $friend->id,
                $friendPlayer
            );
        })->filter()->values();
    }

    /**
     * Sprawdza czy użytkownicy są znajomymi
     * @param int $userId
     * @param int $friendId
     * @return bool
     */
    public function areFriends(int $userId, int $friendId): bool
    {
        return DB::table('friendships')
            ->where(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $userId)
                      ->where('friend_id', $friendId);
            })
            ->orWhere(function ($query) use ($userId, $friendId) {
                $query->where('user_id', $friendId)
                      ->where('friend_id', $userId);
            })
            ->exists();
    }
}
