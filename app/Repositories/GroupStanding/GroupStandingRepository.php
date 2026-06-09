<?php

namespace App\Repositories\GroupStanding;

use App\Domain\GroupStandingDomain;
use App\Models\GroupStanding\GroupStanding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupStandingRepository
{
    public function createEmptyStandings(int $tournamentId, array $groups)
    {
        $standingsToInsert = [];

        foreach ($groups as $index => $group) {
            foreach ($group as $playerId) {
                $standingsToInsert[] = [
                    'tournament_id' => $tournamentId,
                    'group_number' => $index + 1,
                    'player_id' => $playerId,
                    'matches_played' => 0,
                    'matches_won' => 0,
                    'matches_lost' => 0,
                    'legs_won' => 0,
                    'legs_lost' => 0,
                    'points' => 0,
                    'place' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        GroupStanding::insert($standingsToInsert);
    }

    /**
     * @param int $groupNumber
     * @param int $tournamentId
     * @return Collection<int, GroupStandingDomain>
     */
    public function getByGroupNumberAndTournamentId(int $groupNumber, int $tournamentId): Collection
    {

        return GroupStanding::with(['tournament', 'player'])
                ->where('tournament_id', $tournamentId)
                ->where('group_number', $groupNumber)
                ->get()
                ->map(fn($standing) => GroupStandingDomain::fromEloquent($standing, ['tournament', 'player']))
                ->values();
    }

    /**
     * @param Collection<int, GroupStandingDomain> $standings
     * @return void
     */
    public function updatePlaces(Collection $standings): void
    {
        foreach ($standings as $standing) {
            GroupStanding::where('id', $standing->id)
                ->update(['place' => $standing->place]);
        }
    }

    public function resetGroup(int $tournamentId, int $groupNumber): void
    {
        GroupStanding::where('tournament_id', $tournamentId)
            ->where('group_number', $groupNumber)
            ->update([
                'matches_played' => 0,
                'matches_won' => 0,
                'matches_lost' => 0,
                'legs_won' => 0,
                'legs_lost' => 0,
                'points' => 0,
            ]);
    }

    public function updateDetails(int $playerId, bool $hasWon, int $legsWon, int $legsLost, int $tournamentId): void
    {
        GroupStanding::where('player_id', $playerId)
            ->where('tournament_id', $tournamentId)
            ->update([
                'matches_played' => DB::raw('matches_played + 1'),
                'matches_won' => $hasWon ? DB::raw('matches_won + 1') : DB::raw('matches_won'),
                'matches_lost' => !$hasWon ? DB::raw('matches_lost + 1') : DB::raw('matches_lost'),
                'legs_won' => DB::raw("legs_won + $legsWon"),
                'legs_lost' => DB::raw("legs_lost + $legsLost"),
                'points' => $hasWon ? DB::raw('points + 1') : DB::raw('points'),
            ]);
    }

    public function getPlayerIdsToAdvanceFromGroups(int $tournamentId, int $placesPerGroup): Collection
    {
        return $this->getAdvancingPlayersWithGroups($tournamentId, $placesPerGroup)
            ->pluck('player_id');
    }

    /**
     * Awansujący z numerem grupy (do losowania pierwszej rundy playoff).
     *
     * @return Collection<int, array{player_id: int, group_number: int}>
     */
    public function getAdvancingPlayersWithGroups(int $tournamentId, int $placesPerGroup): Collection
    {
        return GroupStanding::where('tournament_id', $tournamentId)
            ->where('place', '<=', $placesPerGroup)
            ->where('place', '>', 0)
            ->orderBy('group_number')
            ->orderBy('place')
            ->get()
            ->map(fn (GroupStanding $standing) => [
                'player_id' => $standing->player_id,
                'group_number' => $standing->group_number,
            ])
            ->values();
    }

    /**
     * @param int $tournamentId
     * @param int $advancePerGroup
     * @return Collection<GroupStandingDomain>
     */
    public function getGroupLosers(int $tournamentId, int $advancePerGroup): Collection
    {
        return GroupStanding::where('tournament_id', $tournamentId)
            ->where('place', '>', $advancePerGroup)
            ->with(['tournament', 'player'])
            ->get()
            ->map(fn($standing) => GroupStandingDomain::fromEloquent($standing, ['tournament', 'player']));
    }
}












