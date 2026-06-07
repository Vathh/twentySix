<?php

namespace App\Domain\Tournament;

use App\Domain\PlayerDomain;
use App\Enums\TournamentInvitationStatus;
use App\Models\Tournament\TournamentInvitation;
use Carbon\Carbon;

class TournamentInvitationDomain
{
    public function __construct(
        public readonly int $id,
        public readonly int $tournamentId,
        public readonly string $tournamentName,
        public readonly int $userId,
        public readonly ?PlayerDomain $userPlayer,
        public readonly int $invitedById,
        public readonly TournamentInvitationStatus $status,
        public readonly ?Carbon $respondedAt,
        public readonly Carbon $createdAt,
    ) {
    }

    public static function fromEloquent(TournamentInvitation $invitation): self
    {
        $invitation->loadMissing(['user.player', 'tournament']);

        return new self(
            id: $invitation->id,
            tournamentId: $invitation->tournament_id,
            tournamentName: $invitation->tournament->name,
            userId: $invitation->user_id,
            userPlayer: PlayerDomain::fromEloquent($invitation->user?->player),
            invitedById: $invitation->invited_by,
            status: $invitation->status,
            respondedAt: $invitation->responded_at,
            createdAt: $invitation->created_at,
        );
    }
}
