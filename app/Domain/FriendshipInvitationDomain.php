<?php

namespace App\Domain;

use App\Models\Friends\FriendshipInvitation;
use Carbon\Carbon;

class FriendshipInvitationDomain
{
    public function __construct(
        public readonly int $id,
        public readonly int $senderId,
        public readonly int $receiverId,
        public readonly ?PlayerDomain $senderPlayer,
        public readonly ?PlayerDomain $receiverPlayer,
        public readonly string $status,
        public readonly ?Carbon $createdAt = null,
    ) {
    }

    public static function fromEloquent(FriendshipInvitation $invitation): self
    {
        $invitation->loadMissing(['sender.player', 'receiver.player']);

        return new self(
            id: $invitation->id,
            senderId: $invitation->sender_id,
            receiverId: $invitation->receiver_id,
            senderPlayer: PlayerDomain::fromEloquent($invitation->sender?->player),
            receiverPlayer: PlayerDomain::fromEloquent($invitation->receiver?->player),
            status: $invitation->status,
            createdAt: $invitation->created_at,
        );
    }
}
