<?php

namespace App\Repositories\Activity;

use App\Enums\TournamentInvitationStatus;
use Illuminate\Support\Facades\DB;

class ProfileCircleRepository
{
    public function shares(int $viewerUserId, int $ownerUserId): bool
    {
        if ($viewerUserId === $ownerUserId) {
            return true;
        }

        $sharedOrganization = DB::table('organization_user as viewer_membership')
            ->join('organization_user as owner_membership', 'viewer_membership.organization_id', '=', 'owner_membership.organization_id')
            ->where('viewer_membership.user_id', $viewerUserId)
            ->where('owner_membership.user_id', $ownerUserId)
            ->exists();

        if ($sharedOrganization) {
            return true;
        }

        return DB::table('tournament_invitations as viewer_invitation')
            ->join('tournament_invitations as owner_invitation', 'viewer_invitation.tournament_id', '=', 'owner_invitation.tournament_id')
            ->where('viewer_invitation.user_id', $viewerUserId)
            ->where('owner_invitation.user_id', $ownerUserId)
            ->where('viewer_invitation.status', TournamentInvitationStatus::ACCEPTED->value)
            ->where('owner_invitation.status', TournamentInvitationStatus::ACCEPTED->value)
            ->exists();
    }
}
