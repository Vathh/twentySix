<?php

namespace App\Services\Tournament;

use App\Domain\Tournament\TournamentInvitationDomain;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\Tournament\Tournament;
use App\Repositories\Tournament\TournamentInvitationRepository;
use App\Services\Push\InvitationPushService;
use Illuminate\Support\Collection;

class TournamentInvitationService
{
    public function __construct(
        private TournamentInvitationRepository $invitationRepository,
        private InvitationPushService $invitationPushService,
    ) {
    }

    /**
     * @return Collection<int, TournamentInvitationDomain>
     */
    public function getForTournament(int $tournamentId): Collection
    {
        return $this->invitationRepository->getForTournament($tournamentId);
    }

    /**
     * @return Collection<int, TournamentInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return $this->invitationRepository->getReceivedForUser($userId);
    }

    /**
     * @param  array<int>  $userIds
     * @return array{sent: int, skipped: int}
     */
    public function sendBulk(int $tournamentId, array $userIds, int $invitedBy): array
    {
        $this->assertTournamentAcceptsInvitations($tournamentId);

        $sent = 0;
        $skipped = 0;

        foreach (array_unique($userIds) as $userId) {
            try {
                $invitation = $this->invitationRepository->createOrReinvite(
                    $tournamentId,
                    (int) $userId,
                    $invitedBy,
                );
                $this->dispatchTournamentPush($invitation);
                $sent++;
            } catch (\RuntimeException) {
                $skipped++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    public function send(int $tournamentId, int $userId, int $invitedBy): TournamentInvitationDomain
    {
        $this->assertTournamentAcceptsInvitations($tournamentId);

        $invitation = $this->invitationRepository->createOrReinvite($tournamentId, $userId, $invitedBy);
        $this->dispatchTournamentPush($invitation);

        return $invitation;
    }

    public function cancel(int $tournamentId, int $invitationId): void
    {
        $this->assertTournamentAcceptsInvitations($tournamentId);
        $this->invitationRepository->cancelPending($invitationId, $tournamentId);
    }

    public function removeParticipant(int $tournamentId, int $invitationId): void
    {
        $this->assertTournamentAcceptsInvitations($tournamentId);
        $this->invitationRepository->removeAccepted($invitationId, $tournamentId);
    }

    public function accept(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->findById($invitationId);

        if ($invitation === null) {
            throw new \RuntimeException('Zaproszenie nie istnieje');
        }

        $this->assertTournamentAcceptsInvitations($invitation->tournamentId);
        $this->invitationRepository->accept($invitationId, $userId);
    }

    public function reject(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->findById($invitationId);

        if ($invitation === null) {
            throw new \RuntimeException('Zaproszenie nie istnieje');
        }

        if ($invitation->status !== TournamentInvitationStatus::PENDING) {
            throw new \RuntimeException('Zaproszenie zostało już przetworzone');
        }

        $this->invitationRepository->reject($invitationId, $userId);
    }

    public function withdraw(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->findById($invitationId);

        if ($invitation === null) {
            throw new \RuntimeException('Zaproszenie nie istnieje');
        }

        $this->assertTournamentAcceptsInvitations($invitation->tournamentId);
        $this->invitationRepository->withdraw($invitationId, $userId);
    }

    /**
     * @return Collection<int, int>
     */
    public function getActiveInvitedUserIds(int $tournamentId): Collection
    {
        return $this->invitationRepository->getActiveInvitedUserIds($tournamentId);
    }

    private function assertTournamentAcceptsInvitations(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if ($tournament->status !== TournamentStatus::CREATED) {
            throw new \RuntimeException('Turniej już wystartował — zaproszenia i zmiany uczestników są zablokowane');
        }
    }

    private function dispatchTournamentPush(TournamentInvitationDomain $invitation): void
    {
        $this->invitationPushService->notifyTournamentInvitation(
            recipientUserId: $invitation->userId,
            invitationId: $invitation->id,
            tournamentName: $invitation->tournamentName,
        );
    }
}
