<?php

namespace App\Domain;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Models\Friends\FriendshipInvitation;
use Carbon\Carbon;

class FriendshipInvitationDomain
{
    use AssertsRelationsLoaded;

    /** Relacje zawsze wymagane — dociągnij w Repository (np. `FriendshipInvitation::with(self::RELATIONS)`). */
    public const RELATIONS = ['sender.player', 'receiver.player'];
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
        self::assertRelationsLoaded($invitation, self::RELATIONS, self::RELATIONS);

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

    /**
     * Reguła: kogo można zaprosić do znajomych (bez wysyłania zaproszenia).
     * Używane np. przy wyświetlaniu profilu gracza, żeby zdecydować, czy pokazać przycisk zaproszenia.
     */
    public static function canInvite(bool $isSelf, bool $areFriends, bool $hasPendingInvitation): bool
    {
        return ! $isSelf && ! $areFriends && ! $hasPendingInvitation;
    }

    /**
     * Waliduje, czy zaproszenie do znajomych może zostać wysłane; rzuca wyjątek z komunikatem dla użytkownika.
     *
     * @throws \RuntimeException
     */
    public static function assertCanSend(int $senderId, int $receiverId, bool $areFriends, bool $hasPendingInvitation): void
    {
        if ($senderId === $receiverId) {
            throw new \RuntimeException('Nie możesz wysłać zaproszenia do siebie');
        }

        if ($areFriends) {
            throw new \RuntimeException('Użytkownicy są już znajomymi');
        }

        if ($hasPendingInvitation) {
            throw new \RuntimeException('Zaproszenie już zostało wysłane');
        }
    }
}
