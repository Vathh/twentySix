<?php

namespace App\Domain\Season;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\PlayerDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\Season\SeasonInvitation;
use Carbon\Carbon;

class SeasonInvitationDomain
{
    use AssertsRelationsLoaded;

    public const RELATIONS = ['user.player', 'season'];

    public function __construct(
        public readonly int $id,
        public readonly int $seasonId,
        public readonly string $seasonName,
        public readonly int $userId,
        public readonly ?PlayerDomain $userPlayer,
        public readonly int $invitedById,
        public readonly OrganizationInvitationStatus $status,
        public readonly ?Carbon $respondedAt,
        public readonly Carbon $createdAt,
    ) {}

    public static function fromEloquent(SeasonInvitation $invitation): self
    {
        self::assertRelationsLoaded($invitation, self::RELATIONS, self::RELATIONS);

        return new self(
            id: $invitation->id,
            seasonId: $invitation->season_id,
            seasonName: $invitation->season->name,
            userId: $invitation->user_id,
            userPlayer: PlayerDomain::fromEloquent($invitation->user?->player),
            invitedById: $invitation->invited_by,
            status: $invitation->status,
            respondedAt: $invitation->responded_at,
            createdAt: $invitation->created_at,
        );
    }
}
