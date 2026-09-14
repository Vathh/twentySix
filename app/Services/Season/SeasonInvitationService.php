<?php

namespace App\Services\Season;

use App\Domain\Season\SeasonInvitationDomain;
use App\Repositories\Season\SeasonInvitationRepository;
use App\Repositories\Season\SeasonRepository;
use App\Services\Push\InvitationPushService;
use Illuminate\Support\Collection;

class SeasonInvitationService
{
    public function __construct(
        private SeasonInvitationRepository $invitationRepository,
        private SeasonRepository $seasonRepository,
        private SeasonService $seasonService,
        private InvitationPushService $invitationPushService,
    ) {}

    /**
     * @return Collection<int, SeasonInvitationDomain>
     */
    public function getPendingForSeason(int $seasonId): Collection
    {
        return $this->invitationRepository->getPendingForSeason($seasonId);
    }

    /**
     * @return Collection<int, SeasonInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return $this->invitationRepository->getReceivedForUser($userId);
    }

    public function send(int $seasonId, int $userId, int $invitedBy): SeasonInvitationDomain
    {
        if ($userId === $invitedBy) {
            throw new \RuntimeException('Nie możesz zaprosić samego siebie');
        }

        $relatedIds = $this->seasonRepository->getRelatedUsers($seasonId)->pluck('id');
        if ($relatedIds->contains($userId)) {
            throw new \RuntimeException('Użytkownik jest już powiązany z tym sezonem');
        }

        $invitation = $this->invitationRepository->createOrReinvite($seasonId, $userId, $invitedBy);
        $this->invitationPushService->notifySeasonInvitation(
            $userId,
            $invitation->id,
            $invitation->seasonName,
        );

        return $invitation;
    }

    public function cancel(int $seasonId, int $invitationId): void
    {
        $this->invitationRepository->cancelPending($invitationId, $seasonId);
    }

    public function accept(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->accept($invitationId, $userId);
        $this->seasonService->addRelatedUser($invitation->seasonId, $userId);
    }

    public function reject(int $invitationId, int $userId): void
    {
        $this->invitationRepository->reject($invitationId, $userId);
    }

    public function removeMember(int $seasonId, int $userId): void
    {
        $this->seasonService->removeRelatedUser($seasonId, $userId);
        $this->invitationRepository->markRemoved($seasonId, $userId);
    }
}
