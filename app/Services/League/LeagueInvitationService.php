<?php

namespace App\Services\League;

use App\Domain\League\LeagueInvitationDomain;
use App\Repositories\League\LeagueInvitationRepository;
use App\Repositories\League\LeagueRepository;
use App\Services\Push\InvitationPushService;
use Illuminate\Support\Collection;

class LeagueInvitationService
{
    public function __construct(
        private LeagueInvitationRepository $invitationRepository,
        private LeagueRepository $leagueRepository,
        private LeagueService $leagueService,
        private InvitationPushService $invitationPushService,
    ) {}

    /**
     * @return Collection<int, LeagueInvitationDomain>
     */
    public function getPendingForLeague(int $leagueId): Collection
    {
        return $this->invitationRepository->getPendingForLeague($leagueId);
    }

    /**
     * @return Collection<int, LeagueInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return $this->invitationRepository->getReceivedForUser($userId);
    }

    public function send(int $leagueId, int $userId, int $invitedBy): LeagueInvitationDomain
    {
        if ($userId === $invitedBy) {
            throw new \RuntimeException('Nie możesz zaprosić samego siebie');
        }

        $relatedIds = $this->leagueRepository->getRelatedUserIds($leagueId);
        if ($relatedIds->contains($userId)) {
            throw new \RuntimeException('Użytkownik jest już powiązany z tą ligą');
        }

        $invitation = $this->invitationRepository->createOrReinvite($leagueId, $userId, $invitedBy);
        $this->invitationPushService->notifyLeagueInvitation(
            $userId,
            $invitation->id,
            $invitation->leagueName,
        );

        return $invitation;
    }

    public function cancel(int $leagueId, int $invitationId): void
    {
        $this->invitationRepository->cancelPending($invitationId, $leagueId);
    }

    public function accept(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->accept($invitationId, $userId);
        $this->leagueService->addRelatedUser($invitation->leagueId, $userId);
    }

    public function reject(int $invitationId, int $userId): void
    {
        $this->invitationRepository->reject($invitationId, $userId);
    }

    public function removeMember(int $leagueId, int $userId): void
    {
        $this->leagueService->removeRelatedUser($leagueId, $userId);
        $this->invitationRepository->markRemoved($leagueId, $userId);
    }
}
