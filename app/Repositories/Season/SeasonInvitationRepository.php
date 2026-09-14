<?php

namespace App\Repositories\Season;

use App\Domain\Season\SeasonInvitationDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\Season\SeasonInvitation;
use Illuminate\Support\Collection;

class SeasonInvitationRepository
{
    public function findById(int $invitationId): ?SeasonInvitationDomain
    {
        $invitation = SeasonInvitation::with(SeasonInvitationDomain::RELATIONS)->find($invitationId);

        return $invitation ? SeasonInvitationDomain::fromEloquent($invitation) : null;
    }

    /**
     * @return Collection<int, SeasonInvitationDomain>
     */
    public function getPendingForSeason(int $seasonId): Collection
    {
        return SeasonInvitation::with(SeasonInvitationDomain::RELATIONS)
            ->where('season_id', $seasonId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SeasonInvitation $invitation) => SeasonInvitationDomain::fromEloquent($invitation));
    }

    /**
     * @return Collection<int, SeasonInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return SeasonInvitation::with(SeasonInvitationDomain::RELATIONS)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SeasonInvitation $invitation) => SeasonInvitationDomain::fromEloquent($invitation));
    }

    public function createOrReinvite(int $seasonId, int $userId, int $invitedBy): SeasonInvitationDomain
    {
        $existing = SeasonInvitation::query()
            ->where('season_id', $seasonId)
            ->where('user_id', $userId)
            ->first();

        if ($existing === null) {
            $invitation = SeasonInvitation::query()->create([
                'season_id' => $seasonId,
                'user_id' => $userId,
                'invited_by' => $invitedBy,
                'status' => OrganizationInvitationStatus::PENDING,
            ]);

            return SeasonInvitationDomain::fromEloquent(
                $invitation->load(SeasonInvitationDomain::RELATIONS)
            );
        }

        if ($existing->status->isActive()) {
            throw new \RuntimeException(
                $existing->status === OrganizationInvitationStatus::PENDING
                    ? 'Użytkownik ma już oczekujące zaproszenie do tego sezonu'
                    : 'Użytkownik jest już powiązany z tym sezonem'
            );
        }

        $existing->update([
            'invited_by' => $invitedBy,
            'status' => OrganizationInvitationStatus::PENDING,
            'responded_at' => null,
        ]);

        return SeasonInvitationDomain::fromEloquent(
            $existing->fresh(SeasonInvitationDomain::RELATIONS)
        );
    }

    public function cancelPending(int $invitationId, int $seasonId): void
    {
        $invitation = SeasonInvitation::query()
            ->where('id', $invitationId)
            ->where('season_id', $seasonId)
            ->firstOrFail();

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw new \RuntimeException('Można anulować tylko zaproszenie oczekujące');
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function markRemoved(int $seasonId, int $userId): void
    {
        SeasonInvitation::query()
            ->where('season_id', $seasonId)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::ACCEPTED)
            ->update([
                'status' => OrganizationInvitationStatus::REMOVED,
                'responded_at' => now(),
            ]);
    }

    public function accept(int $invitationId, int $userId): SeasonInvitationDomain
    {
        $invitation = SeasonInvitation::query()->findOrFail($invitationId);

        if ((int) $invitation->user_id !== $userId) {
            throw new \RuntimeException('Nie możesz zaakceptować tego zaproszenia');
        }

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::ACCEPTED,
            'responded_at' => now(),
        ]);

        return SeasonInvitationDomain::fromEloquent(
            $invitation->fresh(SeasonInvitationDomain::RELATIONS)
        );
    }

    public function reject(int $invitationId, int $userId): void
    {
        $invitation = SeasonInvitation::query()->findOrFail($invitationId);

        if ((int) $invitation->user_id !== $userId) {
            throw new \RuntimeException('Nie możesz odrzucić tego zaproszenia');
        }

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::REJECTED,
            'responded_at' => now(),
        ]);
    }
}
