<?php

namespace App\Repositories\Organization;

use App\Enums\OrganizationJoinRequestStatus;
use App\Models\Organization\OrganizationJoinRequest;
use Illuminate\Support\Collection;

class OrganizationJoinRequestRepository
{
    public function findById(int $id): ?OrganizationJoinRequest
    {
        return OrganizationJoinRequest::with(['user.player', 'organization'])->find($id);
    }

    public function findForUser(int $organizationId, int $userId): ?OrganizationJoinRequest
    {
        return OrganizationJoinRequest::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @return Collection<int, OrganizationJoinRequest>
     */
    public function pendingForOrganization(int $organizationId): Collection
    {
        return OrganizationJoinRequest::with(['user.player'])
            ->where('organization_id', $organizationId)
            ->where('status', OrganizationJoinRequestStatus::PENDING)
            ->orderBy('created_at')
            ->get();
    }

    public function createPending(int $organizationId, int $userId): OrganizationJoinRequest
    {
        return OrganizationJoinRequest::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'status' => OrganizationJoinRequestStatus::PENDING,
        ])->load(['user.player', 'organization']);
    }

    public function markPending(OrganizationJoinRequest $request): OrganizationJoinRequest
    {
        $request->update([
            'status' => OrganizationJoinRequestStatus::PENDING,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        return $request->fresh(['user.player', 'organization']);
    }

    public function markApproved(OrganizationJoinRequest $request, int $adminId): void
    {
        $request->update([
            'status' => OrganizationJoinRequestStatus::APPROVED,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
        ]);
    }

    public function markRejected(OrganizationJoinRequest $request, int $adminId): void
    {
        $request->update([
            'status' => OrganizationJoinRequestStatus::REJECTED,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
        ]);
    }
}
