<?php

namespace App\Services\Organization;

use App\Domain\Organization\OrganizationInvitationDomain;
use App\Domain\RelatedRosterInvite;
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
    ) {}

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

    public function send(int $organizationId, int $userId, int $invitedBy): RelatedRosterInvite
    {
        $relatedIds = $this->organizationRepository->getRelatedUsers($organizationId)->pluck('id');
        if ($relatedIds->contains($userId)) {
            throw new \RuntimeException('Użytkownik jest już powiązany z tą organizacją');
        }

        if ($userId === $invitedBy) {
            return RelatedRosterInvite::member(
                $this->organizationService->addRelatedUser($organizationId, $userId),
            );
        }

        $invitation = $this->invitationRepository->createOrReinvite($organizationId, $userId, $invitedBy);
        $this->invitationPushService->notifyOrganizationInvitation(
            $userId,
            $invitation->id,
            $invitation->organizationName,
        );

        return RelatedRosterInvite::invitation($invitation);
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, 'member'|'pending'|'invitable'>
     */
    public function statesForUsers(int $organizationId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_values(array_filter($userIds, fn (int $id) => $id > 0));
        if ($userIds === []) {
            return [];
        }

        $members = array_flip($this->organizationRepository->relatedIdsAmong($organizationId, $userIds));
        $pending = array_flip($this->invitationRepository->pendingUserIds($organizationId, $userIds));
        $states = [];
        foreach ($userIds as $userId) {
            if (isset($members[$userId])) {
                $states[$userId] = 'member';
            } elseif (isset($pending[$userId])) {
                $states[$userId] = 'pending';
            } else {
                $states[$userId] = 'invitable';
            }
        }

        return $states;
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
        if ($this->organizationRepository->isAdmin($organizationId, $userId)) {
            throw new \RuntimeException(RelatedRosterInvite::ADMIN_MEMBERSHIP_MESSAGE);
        }

        $this->organizationService->removeRelatedUser($organizationId, $userId);
        $this->invitationRepository->markRemoved($organizationId, $userId);
    }

    public function leave(int $organizationId, int $userId): void
    {
        if (! $this->organizationRepository->isRelated($organizationId, $userId)) {
            throw new \RuntimeException('Nie jesteś w składzie tej organizacji.');
        }

        $this->removeMember($organizationId, $userId);
    }
}
