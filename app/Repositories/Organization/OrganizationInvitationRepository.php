<?php

namespace App\Repositories\Organization;

use App\Domain\Organization\OrganizationInvitationDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\Organization\OrganizationInvitation;
use Illuminate\Support\Collection;

class OrganizationInvitationRepository
{
    public function findById(int $invitationId): ?OrganizationInvitationDomain
    {
        $invitation = OrganizationInvitation::with(OrganizationInvitationDomain::RELATIONS)->find($invitationId);

        return $invitation ? OrganizationInvitationDomain::fromEloquent($invitation) : null;
    }

    /**
     * @return Collection<int, OrganizationInvitationDomain>
     */
    public function getPendingForOrganization(int $organizationId): Collection
    {
        return OrganizationInvitation::with(OrganizationInvitationDomain::RELATIONS)
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationInvitation $invitation) => OrganizationInvitationDomain::fromEloquent($invitation));
    }

    /**
     * @return Collection<int, OrganizationInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return OrganizationInvitation::with(OrganizationInvitationDomain::RELATIONS)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationInvitation $invitation) => OrganizationInvitationDomain::fromEloquent($invitation));
    }

    public function createOrReinvite(int $organizationId, int $userId, int $invitedBy): OrganizationInvitationDomain
    {
        $existing = OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->first();

        if ($existing === null) {
            $invitation = OrganizationInvitation::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'invited_by' => $invitedBy,
                'status' => OrganizationInvitationStatus::PENDING,
            ]);

            return OrganizationInvitationDomain::fromEloquent(
                $invitation->load(OrganizationInvitationDomain::RELATIONS)
            );
        }

        if ($existing->status->isActive()) {
            throw new \RuntimeException(
                $existing->status === OrganizationInvitationStatus::PENDING
                    ? 'Użytkownik ma już oczekujące zaproszenie do tej organizacji'
                    : 'Użytkownik jest już powiązany z tą organizacją'
            );
        }

        $existing->update([
            'invited_by' => $invitedBy,
            'status' => OrganizationInvitationStatus::PENDING,
            'responded_at' => null,
        ]);

        return OrganizationInvitationDomain::fromEloquent(
            $existing->fresh(OrganizationInvitationDomain::RELATIONS)
        );
    }

    public function cancelPending(int $invitationId, int $organizationId): void
    {
        $invitation = OrganizationInvitation::query()
            ->where('id', $invitationId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw new \RuntimeException('Można anulować tylko zaproszenie oczekujące');
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function markRemoved(int $organizationId, int $userId): void
    {
        OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::ACCEPTED)
            ->update([
                'status' => OrganizationInvitationStatus::REMOVED,
                'responded_at' => now(),
            ]);
    }

    public function accept(int $invitationId, int $userId): OrganizationInvitationDomain
    {
        $invitation = OrganizationInvitation::query()->findOrFail($invitationId);

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

        return OrganizationInvitationDomain::fromEloquent(
            $invitation->fresh(OrganizationInvitationDomain::RELATIONS)
        );
    }

    public function reject(int $invitationId, int $userId): void
    {
        $invitation = OrganizationInvitation::query()->findOrFail($invitationId);

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
