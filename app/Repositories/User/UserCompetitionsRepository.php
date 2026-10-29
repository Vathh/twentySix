<?php

namespace App\Repositories\User;

use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Collection;

class UserCompetitionsRepository
{
    /**
     * @return Collection<int, Season>
     */
    public function seasonsForUser(int $userId): Collection
    {
        $today = now()->toDateString();

        return Season::query()
            ->with('organization')
            ->where(function ($query) use ($userId) {
                $query->whereHas('admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas('organization.admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas(
                        'tournaments.invitations',
                        fn ($invitations) => $invitations
                            ->where('user_id', $userId)
                            ->where('status', TournamentInvitationStatus::ACCEPTED->value),
                    );
            })
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, League>
     */
    public function leaguesForUser(int $userId, ?int $playerId): Collection
    {
        return League::query()
            ->with(['organization', 'members.division'])
            ->where(function ($query) use ($userId, $playerId) {
                $query->whereHas('organization.admins', fn ($admins) => $admins->where('users.id', $userId));
                if ($playerId !== null) {
                    $query->orWhereHas(
                        'members',
                        fn ($members) => $members->where('player_id', $playerId),
                    );
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Organization>
     */
    public function organizationsForUser(int $userId): Collection
    {
        return Organization::query()
            ->where(function ($query) use ($userId) {
                $query->whereHas('admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas('relatedUsers', fn ($related) => $related->where('users.id', $userId));
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Turnieje niezakończone: admin organizacji, sezonu albo turnieju oraz zaakceptowany uczestnik.
     *
     * @return Collection<int, Tournament>
     */
    public function tournamentsForUser(int $userId): Collection
    {
        return Tournament::query()
            ->with(['organization', 'season.organization', 'admins'])
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', TournamentStatus::FINISHED->value);
            })
            ->where(function ($query) use ($userId) {
                $query->whereHas('admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas('season.admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas('organization.admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas('season.organization.admins', fn ($admins) => $admins->where('users.id', $userId))
                    ->orWhereHas(
                        'invitations',
                        fn ($invitations) => $invitations
                            ->where('user_id', $userId)
                            ->where('status', TournamentInvitationStatus::ACCEPTED->value),
                    );
            })
            ->orderByDesc('date')
            ->orderBy('name')
            ->get();
    }
}
