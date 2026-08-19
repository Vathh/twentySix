<?php

namespace App\Domain\Organization;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\PlayerDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\Organization\OrganizationInvitation;
use Carbon\Carbon;

class OrganizationInvitationDomain
{
    use AssertsRelationsLoaded;

    public const RELATIONS = ['user.player', 'organization'];

    public function __construct(
        public readonly int $id,
        public readonly int $organizationId,
        public readonly string $organizationName,
        public readonly int $userId,
        public readonly ?PlayerDomain $userPlayer,
        public readonly int $invitedById,
        public readonly OrganizationInvitationStatus $status,
        public readonly ?Carbon $respondedAt,
        public readonly Carbon $createdAt,
    ) {
    }

    public static function fromEloquent(OrganizationInvitation $invitation): self
    {
        self::assertRelationsLoaded($invitation, self::RELATIONS, self::RELATIONS);

        return new self(
            id: $invitation->id,
            organizationId: $invitation->organization_id,
            organizationName: $invitation->organization->name,
            userId: $invitation->user_id,
            userPlayer: PlayerDomain::fromEloquent($invitation->user?->player),
            invitedById: $invitation->invited_by,
            status: $invitation->status,
            respondedAt: $invitation->responded_at,
            createdAt: $invitation->created_at,
        );
    }
}
