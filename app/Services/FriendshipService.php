<?php

namespace App\Services;

use App\Domain\FriendshipDomain;
use App\Repositories\FriendshipRepository;
use Illuminate\Support\Collection;

class FriendshipService
{
    public function __construct(
        private FriendshipRepository $friendshipRepository
    )
    {
    }

    /**
     * Dodaje znajomość między dwoma użytkownikami
     * @param int $userId
     * @param int $friendId
     * @return void
     */
    public function addFriend(int $userId, int $friendId): void
    {
        $this->friendshipRepository->addFriendship($userId, $friendId);
    }

    /**
     * Usuwa znajomość między dwoma użytkownikami
     * @param int $userId
     * @param int $friendId
     * @return void
     */
    public function removeFriend(int $userId, int $friendId): void
    {
        $this->friendshipRepository->removeFriendship($userId, $friendId);
    }

    /**
     * Pobiera listę znajomych użytkownika
     * @param int $userId
     * @return Collection<int, FriendshipDomain>
     */
    public function getFriends(int $userId): Collection
    {
        return $this->friendshipRepository->getFriends($userId);
    }

    /**
     * Sprawdza czy użytkownicy są znajomymi
     * @param int $userId
     * @param int $friendId
     * @return bool
     */
    public function areFriends(int $userId, int $friendId): bool
    {
        return $this->friendshipRepository->areFriends($userId, $friendId);
    }
}
