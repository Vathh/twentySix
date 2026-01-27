<?php

namespace App\Domain;

class FriendshipDomain
{
    /**
     * @param int $id
     * @param int $userId
     * @param int $friendId
     * @param PlayerDomain $friendPlayer
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $friendId,
        public readonly PlayerDomain $friendPlayer
    )
    {
    }

    /**
     * @param int $friendshipId
     * @param int $userId
     * @param int $friendId
     * @param PlayerDomain $friendPlayer
     * @return FriendshipDomain
     */
    public static function fromData(int $friendshipId, int $userId, int $friendId, PlayerDomain $friendPlayer): self
    {
        return new self(
            id: $friendshipId,
            userId: $userId,
            friendId: $friendId,
            friendPlayer: $friendPlayer
        );
    }
}
