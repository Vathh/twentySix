<?php

namespace App\Services\Friends;

use App\Domain\FriendshipDomain;
use App\Domain\FriendshipInvitationDomain;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Friends\FriendshipInvitationRepository;
use Illuminate\Support\Collection;

class FriendshipService
{
    public function __construct(
        private FriendshipRepository $friendshipRepository,
        private FriendshipInvitationRepository $invitationRepository
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

    /**
     * Wysyła zaproszenie do znajomych
     * @param int $senderId
     * @param int $receiverId
     * @return FriendshipInvitationDomain
     */
    public function sendInvitation(int $senderId, int $receiverId): FriendshipInvitationDomain
    {
        // Sprawdź czy nie są już znajomymi
        if ($this->friendshipRepository->areFriends($senderId, $receiverId)) {
            throw new \RuntimeException('Użytkownicy są już znajomymi');
        }

        // Sprawdź czy nie ma już zaproszenia
        if ($this->invitationRepository->hasPendingInvitation($senderId, $receiverId)) {
            throw new \RuntimeException('Zaproszenie już zostało wysłane');
        }

        return $this->invitationRepository->create($senderId, $receiverId);
    }

    /**
     * Akceptuje zaproszenie do znajomych
     * @param int $invitationId
     * @param int $userId ID użytkownika który akceptuje
     * @return void
     */
    public function acceptInvitation(int $invitationId, int $userId): void
    {
        $this->invitationRepository->accept($invitationId, $userId);
    }

    /**
     * Odrzuca zaproszenie do znajomych
     * @param int $invitationId
     * @param int $userId ID użytkownika który odrzuca
     * @return void
     */
    public function rejectInvitation(int $invitationId, int $userId): void
    {
        $this->invitationRepository->reject($invitationId, $userId);
    }

    /**
     * Pobiera zaproszenia otrzymane przez użytkownika
     * @param int $userId
     * @return Collection<int, FriendshipInvitationDomain>
     */
    public function getReceivedInvitations(int $userId): Collection
    {
        return $this->invitationRepository->getReceivedInvitations($userId);
    }

    /**
     * Pobiera zaproszenia wysłane przez użytkownika
     * @param int $userId
     * @return Collection<int, FriendshipInvitationDomain>
     */
    public function getSentInvitations(int $userId): Collection
    {
        return $this->invitationRepository->getSentInvitations($userId);
    }
}











