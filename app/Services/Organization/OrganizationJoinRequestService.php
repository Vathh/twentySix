<?php

namespace App\Services\Organization;

use App\Enums\OrganizationJoinRequestStatus;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationJoinRequest;
use App\Models\Users\User;
use App\Repositories\Organization\OrganizationInvitationRepository;
use App\Repositories\Organization\OrganizationJoinRequestRepository;
use App\Repositories\Organization\OrganizationRepository;
use RuntimeException;

class OrganizationJoinRequestService
{
    public function __construct(
        private OrganizationJoinRequestRepository $joinRequests,
        private OrganizationInvitationRepository $invitations,
        private OrganizationRepository $organizations,
        private OrganizationService $organizationService,
    ) {}

    /**
     * @return array{
     *     canApply: bool,
     *     reason: ?string,
     *     alreadyMember: bool,
     *     alreadyPending: bool,
     *     hasInvitation: bool
     * }
     */
    public function statusForUser(Organization $organization, ?User $user): array
    {
        $empty = [
            'canApply' => false,
            'reason' => null,
            'alreadyMember' => false,
            'alreadyPending' => false,
            'hasInvitation' => false,
        ];

        if ($user === null) {
            return $empty;
        }

        if ($this->organizations->isRelated($organization->id, $user->id)) {
            return [
                ...$empty,
                'alreadyMember' => true,
                'reason' => 'Jesteś w składzie tej organizacji.',
            ];
        }

        if ($this->invitations->findPendingForPair($organization->id, $user->id) !== null) {
            return [
                ...$empty,
                'hasInvitation' => true,
                'reason' => 'Masz zaproszenie do tej organizacji.',
            ];
        }

        $user->loadMissing('player');
        if ($user->player === null) {
            return [
                ...$empty,
                'reason' => 'Aby zgłosić się do organizacji, potrzebujesz profilu gracza.',
            ];
        }

        $existing = $this->joinRequests->findForUser($organization->id, $user->id);
        if ($existing !== null && $existing->status === OrganizationJoinRequestStatus::PENDING) {
            return [
                ...$empty,
                'alreadyPending' => true,
                'reason' => 'Zgłoszenie wysłane. Czekasz na decyzję organizatora.',
            ];
        }

        return [
            ...$empty,
            'canApply' => true,
        ];
    }

    public function apply(Organization $organization, User $user): OrganizationJoinRequest
    {
        $user->loadMissing('player');
        if ($user->player === null) {
            throw new RuntimeException('Aby zgłosić się do organizacji, potrzebujesz profilu gracza.');
        }

        if ($this->organizations->isRelated($organization->id, $user->id)) {
            throw new RuntimeException('Jesteś w składzie tej organizacji.');
        }

        if ($this->invitations->findPendingForPair($organization->id, $user->id) !== null) {
            throw new RuntimeException('Masz zaproszenie do tej organizacji.');
        }

        $existing = $this->joinRequests->findForUser($organization->id, $user->id);
        if ($existing !== null && $existing->status === OrganizationJoinRequestStatus::PENDING) {
            return $existing;
        }

        if ($existing !== null) {
            return $this->joinRequests->markPending($existing);
        }

        return $this->joinRequests->createPending($organization->id, $user->id);
    }

    public function approve(Organization $organization, int $requestId, int $adminId): void
    {
        $request = $this->requestForOrganization($organization->id, $requestId);
        if ($request->status !== OrganizationJoinRequestStatus::PENDING) {
            throw new RuntimeException('Zgłoszenie zostało już przetworzone.');
        }

        if (! $this->organizations->isRelated($organization->id, (int) $request->user_id)) {
            $this->organizationService->addRelatedUser($organization->id, (int) $request->user_id);
        }

        $this->joinRequests->markApproved($request, $adminId);
    }

    public function reject(Organization $organization, int $requestId, int $adminId): void
    {
        $request = $this->requestForOrganization($organization->id, $requestId);
        if ($request->status !== OrganizationJoinRequestStatus::PENDING) {
            throw new RuntimeException('Zgłoszenie zostało już przetworzone.');
        }

        $this->joinRequests->markRejected($request, $adminId);
    }

    /**
     * @return list<array{id: int, name: string, userId: int}>
     */
    public function pendingRows(int $organizationId): array
    {
        return $this->joinRequests->pendingForOrganization($organizationId)
            ->map(fn (OrganizationJoinRequest $request) => [
                'id' => (int) $request->id,
                'userId' => (int) $request->user_id,
                'name' => $request->user?->player?->name ?? '—',
            ])
            ->values()
            ->all();
    }

    private function requestForOrganization(int $organizationId, int $requestId): OrganizationJoinRequest
    {
        $request = $this->joinRequests->findById($requestId);
        if ($request === null || (int) $request->organization_id !== $organizationId) {
            throw new RuntimeException('Zgłoszenie nie istnieje.');
        }

        return $request;
    }
}
