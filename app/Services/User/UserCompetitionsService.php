<?php

namespace App\Services\User;

use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\User\UserCompetitionsRepository;

class UserCompetitionsService
{
    public function __construct(
        private UserCompetitionsRepository $userCompetitionsRepository,
    ) {}

    /**
     * @return array{
     *     tournaments: list<array<string, mixed>>,
     *     seasons: list<array<string, mixed>>,
     *     leagues: list<array<string, mixed>>,
     *     organizations: list<array<string, mixed>>
     * }
     */
    public function forUser(User $user, bool $withUrls = false): array
    {
        $userId = (int) $user->id;
        $playerId = $user->player?->id !== null ? (int) $user->player->id : null;

        $adminOrganizationIds = $user->adminOrganizations()->pluck('organizations.id')->map(fn ($id) => (int) $id)->all();
        $adminSeasonIds = $user->adminSeasons()->pluck('seasons.id')->map(fn ($id) => (int) $id)->all();

        $seasons = $this->userCompetitionsRepository->seasonsForUser($userId)
            ->map(function (Season $season) use ($adminSeasonIds, $adminOrganizationIds, $withUrls) {
                $isAdmin = in_array((int) $season->id, $adminSeasonIds, true)
                    || in_array((int) $season->organization_id, $adminOrganizationIds, true);

                $row = [
                    'id' => $season->id,
                    'name' => $season->name,
                    'organizationId' => $season->organization_id,
                    'organizationName' => $season->organization?->name,
                    'startDate' => $season->start_date?->format('Y-m-d'),
                    'endDate' => $season->end_date?->format('Y-m-d'),
                    'role' => $isAdmin ? 'admin' : 'member',
                    'roleLabel' => $isAdmin ? 'Admin' : 'Skład',
                ];
                if ($withUrls) {
                    $row['url'] = route('seasons.show', $season);
                }

                return $row;
            })
            ->values()
            ->all();

        $leagues = $this->userCompetitionsRepository->leaguesForUser($userId, $playerId)
            ->map(function (League $league) use ($adminOrganizationIds, $playerId, $withUrls) {
                $isAdmin = in_array((int) $league->organization_id, $adminOrganizationIds, true);
                $divisionName = null;
                if ($playerId !== null) {
                    $member = $league->members->firstWhere('player_id', $playerId);
                    $divisionName = $member?->division?->name;
                }

                $row = [
                    'id' => $league->id,
                    'name' => $league->name,
                    'organizationId' => $league->organization_id,
                    'organizationName' => $league->organization?->name,
                    'divisionName' => $divisionName,
                    'role' => $isAdmin ? 'admin' : 'member',
                    'roleLabel' => $isAdmin ? 'Admin' : 'Zawodnik',
                ];
                if ($withUrls) {
                    $row['url'] = route('leagues.show', $league);
                }

                return $row;
            })
            ->values()
            ->all();

        $organizations = $this->userCompetitionsRepository->organizationsForUser($userId)
            ->map(function (Organization $organization) use ($adminOrganizationIds, $withUrls) {
                $isAdmin = in_array((int) $organization->id, $adminOrganizationIds, true);
                $row = [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'description' => $organization->description,
                    'role' => $isAdmin ? 'admin' : 'member',
                    'roleLabel' => $isAdmin ? 'Admin' : 'Skład',
                ];
                if ($withUrls) {
                    $row['url'] = route('organizations.show', $organization);
                }

                return $row;
            })
            ->values()
            ->all();

        $tournaments = $this->userCompetitionsRepository->tournamentsForUser($userId)
            ->map(function (Tournament $tournament) use ($adminSeasonIds, $adminOrganizationIds, $userId, $withUrls) {
                $host = $tournament->hostOrganization();
                $isAdmin = $tournament->admins->contains('id', $userId)
                    || ($tournament->season_id !== null && in_array((int) $tournament->season_id, $adminSeasonIds, true))
                    || ($host !== null && in_array((int) $host->id, $adminOrganizationIds, true));

                $row = [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'organizationId' => $host?->id,
                    'organizationName' => $host?->name,
                    'seasonId' => $tournament->season_id,
                    'seasonName' => $tournament->season?->name,
                    'date' => $tournament->date?->format('Y-m-d'),
                    'status' => $tournament->status?->value,
                    'statusLabel' => $tournament->status?->label() ?? 'Zaplanowany',
                    'role' => $isAdmin ? 'admin' : 'member',
                    'roleLabel' => $isAdmin ? 'Admin' : 'Zawodnik',
                ];
                if ($withUrls) {
                    $row['url'] = route('tournaments.show', $tournament);
                }

                return $row;
            })
            ->values()
            ->all();

        return [
            'tournaments' => $tournaments,
            'seasons' => $seasons,
            'leagues' => $leagues,
            'organizations' => $organizations,
        ];
    }
}
