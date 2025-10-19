<?php
namespace App\Repositories;

use App\Domain\SeasonDomain;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

class SeasonRepository
{
    public function create(int $leagueId,
                           string $name,
                           array $adminsIds = [],
                           ?string $startDate = null,
                           ?string $endDate = null): SeasonDomain
    {
        return DB::transaction(function () use ($leagueId, $name, $adminsIds, $startDate, $endDate) {

            $season = Season::create([
               'league_id' => $leagueId,
               'name' => $name,
               'start_date' => $startDate,
               'end_date' => $endDate
            ]);

            if(!empty($adminsIds)) {
                $season->admins()->attach($adminsIds);
            }

            return SeasonDomain::fromEloquentWithAdmins($season);
        });
    }

    public function addAdmin(int $seasonId, int $userId): void
    {
        $season = Season::findOrFail($seasonId);
        $season->admins()->attach($userId);
    }
}
