<?php

namespace App\Repositories\League;

use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Models\League\LeagueDivision;
use App\Models\League\LeagueDivisionMember;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueRepository
{
    public function find(int $leagueId): League
    {
        return League::query()->findOrFail($leagueId);
    }

    public function findWithOrganizationAdmins(int $leagueId): League
    {
        return League::query()
            ->with(['organization.admins', 'divisions.members.player', 'seasons'])
            ->findOrFail($leagueId);
    }

    public function findWithRoster(int $leagueId): League
    {
        return League::query()
            ->with([
                'organization.admins',
                'organization.relatedUsers.player',
                'organization.guests',
                'divisions.members.player',
                'seasons',
            ])
            ->findOrFail($leagueId);
    }

    /**
     * @return Collection<int, League>
     */
    public function listForOrganization(int $organizationId): Collection
    {
        return League::query()
            ->where('organization_id', $organizationId)
            ->with(['divisions', 'seasons'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $divisions
     */
    public function create(int $organizationId, string $name, ?string $description, array $divisions): League
    {
        return DB::transaction(function () use ($organizationId, $name, $description, $divisions) {
            $league = League::query()->create([
                'organization_id' => $organizationId,
                'name' => $name,
                'description' => $description,
            ]);

            foreach (array_values($divisions) as $position => $division) {
                $league->divisions()->create([
                    'position' => $position,
                    'name' => $division['name'],
                    'capacity' => $division['capacity'],
                    'starting_score' => $division['starting_score'],
                    'legs_to_win_set' => $division['legs_to_win_set'],
                    'sets_to_win_match' => $division['sets_to_win_match'],
                    'game_type' => $division['game_type'] ?? 'x01',
                    'promote_direct' => $position === 0 ? 0 : ($division['promote_direct'] ?? 0),
                    'promote_playoff' => $position === 0 ? 0 : ($division['promote_playoff'] ?? 0),
                ]);
            }

            return $league->fresh(['divisions']);
        });
    }

    public function updateDetails(int $leagueId, string $name, ?string $description): void
    {
        League::query()->whereKey($leagueId)->update([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $divisions
     */
    public function replaceDivisions(int $leagueId, array $divisions): void
    {
        DB::transaction(function () use ($leagueId, $divisions) {
            $league = League::query()->with('divisions.members')->findOrFail($leagueId);
            $existing = $league->divisions->keyBy('id');
            $keptIds = [];

            foreach (array_values($divisions) as $position => $input) {
                $id = isset($input['id']) ? (int) $input['id'] : 0;
                $payload = [
                    'position' => $position,
                    'name' => $input['name'],
                    'capacity' => $input['capacity'],
                    'starting_score' => $input['starting_score'],
                    'legs_to_win_set' => $input['legs_to_win_set'],
                    'sets_to_win_match' => $input['sets_to_win_match'],
                    'game_type' => $input['game_type'] ?? 'x01',
                    'promote_direct' => $position === 0 ? 0 : ($input['promote_direct'] ?? 0),
                    'promote_playoff' => $position === 0 ? 0 : ($input['promote_playoff'] ?? 0),
                ];

                if ($id > 0 && $existing->has($id)) {
                    $existing->get($id)->update($payload);
                    $keptIds[] = $id;
                    continue;
                }

                $created = $league->divisions()->create($payload);
                $keptIds[] = $created->id;
            }

            $toDelete = $existing->keys()->diff($keptIds);
            foreach ($toDelete as $deleteId) {
                $division = $existing->get($deleteId);
                if ($division && $division->members->isNotEmpty()) {
                    throw new \DomainException('Nie można usunąć szczebla, który ma zawodników. Najpierw przenieś skład.');
                }
                LeagueDivision::query()->whereKey($deleteId)->delete();
            }
        });
    }

    public function assignPlayer(int $leagueId, int $divisionId, int $playerId): void
    {
        DB::transaction(function () use ($leagueId, $divisionId, $playerId) {
            LeagueDivisionMember::query()
                ->where('league_id', $leagueId)
                ->where('player_id', $playerId)
                ->delete();

            LeagueDivisionMember::query()->create([
                'league_id' => $leagueId,
                'league_division_id' => $divisionId,
                'player_id' => $playerId,
            ]);
        });
    }

    public function removePlayer(int $leagueId, int $playerId): void
    {
        LeagueDivisionMember::query()
            ->where('league_id', $leagueId)
            ->where('player_id', $playerId)
            ->delete();
    }

    /**
     * @param  array<int, list<int>>  $playerIdsByDivisionId
     */
    public function replaceRoster(int $leagueId, array $playerIdsByDivisionId): void
    {
        DB::transaction(function () use ($leagueId, $playerIdsByDivisionId) {
            LeagueDivisionMember::query()->where('league_id', $leagueId)->delete();
            foreach ($playerIdsByDivisionId as $divisionId => $playerIds) {
                foreach ($playerIds as $playerId) {
                    LeagueDivisionMember::query()->create([
                        'league_id' => $leagueId,
                        'league_division_id' => $divisionId,
                        'player_id' => $playerId,
                    ]);
                }
            }
        });
    }

    public function hasOpenSeason(int $leagueId): bool
    {
        return LeagueSeason::query()
            ->where('league_id', $leagueId)
            ->whereIn('status', [
                LeagueSeasonStatus::DRAFT->value,
                LeagueSeasonStatus::IN_PROGRESS->value,
                LeagueSeasonStatus::PLAYOFFS->value,
            ])
            ->exists();
    }

    /**
     * @return Collection<int, Player>
     */
    public function eligiblePlayers(int $organizationId): Collection
    {
        $organization = Organization::query()
            ->with(['relatedUsers.player', 'guests'])
            ->findOrFail($organizationId);

        return $organization->relatedUsers
            ->map(fn ($user) => $user->player)
            ->filter()
            ->concat($organization->guests)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
