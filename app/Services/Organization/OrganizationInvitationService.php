<?php

namespace App\Services\Organization;

use App\Domain\Organization\OrganizationInvitationDomain;
use App\Repositories\Organization\OrganizationInvitationRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Services\Push\InvitationPushService;
use Illuminate\Support\Collection;

class OrganizationInvitationService
{
    public function __construct(
        private OrganizationInvitationRepository $invitationRepository,
        private OrganizationRepository $organizationRepository,
        private OrganizationService $organizationService,
        private InvitationPushService $invitationPushService,
    ) {
    }

    /**
     * @return Collection<int, OrganizationInvitationDomain>
     */
    public function getPendingForOrganization(int $organizationId): Collection
    {
        return $this->invitationRepository->getPendingForOrganization($organizationId);
    }

    /**
     * @return Collection<int, OrganizationInvitationDomain>
     */
    public function getReceivedForUser(int $userId): Collection
    {
        return $this->invitationRepository->getReceivedForUser($userId);
    }

    public function send(int $organizationId, int $userId, int $invitedBy): OrganizationInvitationDomain
    {
        if ($userId === $invitedBy) {
            throw new \RuntimeException('Nie możesz zaprosić samego siebie');
        }

        $relatedIds = $this->organizationRepository->getRelatedUsers($organizationId)->pluck('id');
        if ($relatedIds->contains($userId)) {
            throw new \RuntimeException('Użytkownik jest już powiązany z tą organizacją');
        }

        $invitation = $this->invitationRepository->createOrReinvite($organizationId, $userId, $invitedBy);
        $this->invitationPushService->notifyOrganizationInvitation(
            $userId,
            $invitation->id,
            $invitation->organizationName,
        );

        return $invitation;
    }

    public function cancel(int $organizationId, int $invitationId): void
    {
        $this->invitationRepository->cancelPending($invitationId, $organizationId);
    }

    public function accept(int $invitationId, int $userId): void
    {
        $invitation = $this->invitationRepository->accept($invitationId, $userId);
        $this->organizationService->addRelatedUser($invitation->organizationId, $userId);
    }

    public function reject(int $invitationId, int $userId): void
    {
        $this->invitationRepository->reject($invitationId, $userId);
    }

    public function removeMember(int $organizationId, int $userId): void
    {
        $this->organizationService->removeRelatedUser($organizationId, $userId);
        $this->invitationRepository->markRemoved($organizationId, $userId);
    }
}
