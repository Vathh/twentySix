<?php

namespace App\Repositories\Friends;

use App\Domain\FriendshipInvitationDomain;
use App\Models\Friends\FriendshipInvitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FriendshipInvitationRepository
{
    public function __construct(private FriendshipRepository $friendships) {}
    /**
     * Tworzy zaproszenie do znajomych
     *
     * @throws \RuntimeException jeśli zaproszenie już istnieje
     */
    public function create(int $senderId, int $receiverId): FriendshipInvitationDomain
    {
        // Sprawdź czy zaproszenie już istnieje
        $existing = FriendshipInvitation::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new \RuntimeException('Zaproszenie już zostało wysłane');
        }

        // Sprawdź czy nie ma już zaproszenia w drugą stronę
        $reverseExisting = FriendshipInvitation::where('sender_id', $receiverId)
            ->where('receiver_id', $senderId)
            ->where('status', 'pending')
            ->first();

        if ($reverseExisting) {
            throw new \RuntimeException('Otrzymałeś już zaproszenie od tego użytkownika');
        }

        $invitation = FriendshipInvitation::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'status' => 'pending',
        ]);

        $invitation->load(['sender.player', 'receiver.player']);

        return FriendshipInvitationDomain::fromEloquent($invitation);
    }

    /**
     * Akceptuje zaproszenie
     *
     * @param  int  $receiverId  ID użytkownika który akceptuje (musi być receiverem)
     *
     * @throws \RuntimeException
     */
    public function accept(int $invitationId, int $receiverId): void
    {
        $invitation = FriendshipInvitation::findOrFail($invitationId);

        if ($invitation->receiver_id !== $receiverId) {
            throw new \RuntimeException('Nie możesz zaakceptować tego zaproszenia');
        }

        if ($invitation->status !== 'pending') {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        DB::transaction(function () use ($invitation) {
            // Oznacz zaproszenie jako zaakceptowane
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $this->friendships->addFriendship((int) $invitation->sender_id, (int) $invitation->receiver_id);
        });
    }

    /**
     * Odrzuca zaproszenie
     *
     * @param  int  $receiverId  ID użytkownika który odrzuca (musi być receiverem)
     *
     * @throws \RuntimeException
     */
    public function reject(int $invitationId, int $receiverId): void
    {
        $invitation = FriendshipInvitation::findOrFail($invitationId);

        if ($invitation->receiver_id !== $receiverId) {
            throw new \RuntimeException('Nie możesz odrzucić tego zaproszenia');
        }

        if ($invitation->status !== 'pending') {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $invitation->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);
    }

    /**
     * Pobiera zaproszenia otrzymane przez użytkownika
     *
     * @return Collection<int, FriendshipInvitationDomain>
     */
    public function getReceivedInvitations(int $userId): Collection
    {
        return FriendshipInvitation::where('receiver_id', $userId)
            ->where('status', 'pending')
            ->with(['sender.player', 'receiver.player'])
            ->get()
            ->map(fn ($invitation) => FriendshipInvitationDomain::fromEloquent($invitation));
    }

    /**
     * Pobiera zaproszenia wysłane przez użytkownika
     *
     * @return Collection<int, FriendshipInvitationDomain>
     */
    public function getSentInvitations(int $userId): Collection
    {
        return FriendshipInvitation::where('sender_id', $userId)
            ->where('status', 'pending')
            ->with(['sender.player', 'receiver.player'])
            ->get()
            ->map(fn ($invitation) => FriendshipInvitationDomain::fromEloquent($invitation));
    }

    public function findPending(int $senderId, int $receiverId): ?FriendshipInvitationDomain
    {
        $invitation = FriendshipInvitation::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->with(['sender.player', 'receiver.player'])
            ->first();

        return $invitation
            ? FriendshipInvitationDomain::fromEloquent($invitation)
            : null;
    }

    /**
     * Sprawdza czy istnieje zaproszenie między użytkownikami
     */
    public function hasPendingInvitation(int $userId1, int $userId2): bool
    {
        return FriendshipInvitation::where(function ($query) use ($userId1, $userId2) {
            $query->where('sender_id', $userId1)
                ->where('receiver_id', $userId2);
        })
            ->orWhere(function ($query) use ($userId1, $userId2) {
                $query->where('sender_id', $userId2)
                    ->where('receiver_id', $userId1);
            })
            ->where('status', 'pending')
            ->exists();
    }
}
