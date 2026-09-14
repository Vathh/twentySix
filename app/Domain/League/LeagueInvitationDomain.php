<?php

namespace App\Domain\League;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Domain\PlayerDomain;
use App\Enums\OrganizationInvitationStatus;
use App\Models\League\LeagueInvitation;
use Carbon\Carbon;

class LeagueInvitationDomain
{
    use AssertsRelationsLoaded;

    public const RELATIONS = ['user.player', 'league'];

    public function __construct(
        public readonly int $id,
        public readonly int $leagueId,
        public readonly string $leagueName,
        public readonly int $userId,
        public readonly ?PlayerDomain $userPlayer,
        public readonly int $invitedById,
        public readonly OrganizationInvitationStatus $status,
        public readonly ?Carbon $respondedAt,
        public readonly Carbon $createdAt,
    ) {}

    public static function fromEloquent(LeagueInvitation $invitation): self
    {
        self::assertRelationsLoaded($invitation, self::RELATIONS, self::RELATIONS);

        return new self(
            id: $invitation->id,
            leagueId: $invitation->league_id,
            leagueName: $invitation->league->name,
            userId: $invitation->user_id,
            userPlayer: PlayerDomain::fromEloquent($invitation->user?->player),
            invitedById: $invitation->invited_by,
            status: $invitation->status,
            respondedAt: $invitation->responded_at,
            createdAt: $invitation->created_at,
        );
    }
}
