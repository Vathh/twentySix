<?php

namespace App\Repositories\League;

use App\Domain\League\LeagueInvitationDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\League\LeagueInvitation;
use Illuminate\Support\Collection;

class LeagueInvitationRepository
{
    public function findById(int $invitationId): ?LeagueInvitationDomain
    {
        $invitation = LeagueInvitation::with(LeagueInvitationDomain::RELATIONS)->find($invitationId);

        return $invitation ? LeagueInvitationDomain::fromEloquent($invitation) : null;
    }

    /**
     * @return Collection<int, LeagueInvitationDomain>
     */
    public function getPendingForLeague(int $leagueId): Collection
    {
        return LeagueInvitation::with(LeagueInvitationDomain::RELATIONS)
            ->where('league_id', $leagueId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeagueInvitation $invitation) => LeagueInvitationDomain::fromEloquent($invitation));
    }

    /**
     * @return Collection<int, LeagueInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return LeagueInvitation::with(LeagueInvitationDomain::RELATIONS)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LeagueInvitation $invitation) => LeagueInvitationDomain::fromEloquent($invitation));
    }

    public function createOrReinvite(int $leagueId, int $userId, int $invitedBy): LeagueInvitationDomain
    {
        $existing = LeagueInvitation::query()
            ->where('league_id', $leagueId)
            ->where('user_id', $userId)
            ->first();

        if ($existing === null) {
            $invitation = LeagueInvitation::query()->create([
                'league_id' => $leagueId,
                'user_id' => $userId,
                'invited_by' => $invitedBy,
                'status' => OrganizationInvitationStatus::PENDING,
            ]);

            return LeagueInvitationDomain::fromEloquent(
                $invitation->load(LeagueInvitationDomain::RELATIONS)
            );
        }

        if ($existing->status->isActive()) {
            throw new \RuntimeException(
                $existing->status === OrganizationInvitationStatus::PENDING
                    ? 'Użytkownik ma już oczekujące zaproszenie do tej ligi'
                    : 'Użytkownik jest już powiązany z tą ligą'
            );
        }

        $existing->update([
            'invited_by' => $invitedBy,
            'status' => OrganizationInvitationStatus::PENDING,
            'responded_at' => null,
        ]);

        return LeagueInvitationDomain::fromEloquent(
            $existing->fresh(LeagueInvitationDomain::RELATIONS)
        );
    }

    public function cancelPending(int $invitationId, int $leagueId): void
    {
        $invitation = LeagueInvitation::query()
            ->where('id', $invitationId)
            ->where('league_id', $leagueId)
            ->firstOrFail();

        if ($invitation->status !== OrganizationInvitationStatus::PENDING) {
            throw new \RuntimeException('Można anulować tylko zaproszenie oczekujące');
        }

        $invitation->update([
            'status' => OrganizationInvitationStatus::CANCELLED,
            'responded_at' => now(),
        ]);
    }

    public function markRemoved(int $leagueId, int $userId): void
    {
        LeagueInvitation::query()
            ->where('league_id', $leagueId)
            ->where('user_id', $userId)
            ->where('status', OrganizationInvitationStatus::ACCEPTED)
            ->update([
                'status' => OrganizationInvitationStatus::REMOVED,
                'responded_at' => now(),
            ]);
    }

    public function accept(int $invitationId, int $userId): LeagueInvitationDomain
    {
        $invitation = LeagueInvitation::query()->findOrFail($invitationId);

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

        return LeagueInvitationDomain::fromEloquent(
            $invitation->fresh(LeagueInvitationDomain::RELATIONS)
        );
    }

    public function reject(int $invitationId, int $userId): void
    {
        $invitation = LeagueInvitation::query()->findOrFail($invitationId);

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
