<?php

namespace App\Repositories\Season;

use App\Domain\SeasonDomain;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SeasonRepository
{

    /**
     * @return Collection<int, SeasonDomain>
     */
    public function getAll(): Collection
    {
        return Season::all()->map(fn($season) => SeasonDomain::fromEloquent($season));
    }

    /**
     * @throws Throwable
     */
    public function create(
        ?int     $leagueId,
        string  $name,
        array   $adminsIds = [],
        ?string $startDate = null,
        ?string $endDate = null)
    : void
    {
        DB::transaction(function () use ($leagueId, $name, $adminsIds, $startDate, $endDate) {

            $season = Season::create([
                'league_id' => $leagueId,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            if (!empty($adminsIds)) {
                $season->admins()->attach($adminsIds);
            }
        });
    }

    /**
     * @param int $seasonId
     * @return Collection<int, User>
     */
    public function getRelatedUsers(int $seasonId): Collection
    {
        $season = Season::with(['league.relatedUsers.player', 'relatedUsers.player'])->findOrFail($seasonId);
        $seasonRelatedUsers = $season->relatedUsers;
        $leagueRelatedUsers = $season->league->relatedUsers;

        return $seasonRelatedUsers
                    ->merge($leagueRelatedUsers)
                    ->unique('id')
                    ->values();
    }

    public function addRelatedUser(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->relatedUsers()->attach($userId);
    }

    public function removeRelatedUser(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->relatedUsers()->detach($userId);
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->attach($userId);
    }

    public function removeAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->detach($userId);
    }

    /**
     * @param int $seasonId
     * @return SeasonDomain
     */
    public function findByIdWithLeagueAndGuests(int $seasonId): SeasonDomain
    {
        $season = Season::with(['league', 'guests'])->findOrFail($seasonId);
        return SeasonDomain::fromEloquent($season, ['league', 'guests']);
    }
}











