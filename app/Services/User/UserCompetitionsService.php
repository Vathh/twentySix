<?php

namespace App\Services\User;

use App\Models\Game\Game;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\Organization\Organization;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\User\UserCompetitionsRepository;
use DateTimeInterface;
use Illuminate\Support\Collection;

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

        $seasonModels = $this->userCompetitionsRepository->seasonsForUser($userId);
        $leagueModels = $this->userCompetitionsRepository->leaguesForUser($userId, $playerId);
        $organizationModels = $this->userCompetitionsRepository->organizationsForUser($userId);
        $tournamentModels = $this->userCompetitionsRepository->tournamentsForUser($userId);
        $activity = $this->activityIndex($tournamentModels, $seasonModels, $leagueModels, $organizationModels);

        $seasonModels = $this->sortByActivity($seasonModels, $activity['seasons']);
        $leagueModels = $this->sortByActivity($leagueModels, $activity['leagues']);
        $organizationModels = $this->sortByActivity($organizationModels, $activity['organizations']);
        $tournamentModels = $this->sortByActivity($tournamentModels, $activity['tournaments']);

        $seasons = $seasonModels
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

        $leagues = $leagueModels
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

        $organizations = $organizationModels
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

        $tournaments = $tournamentModels
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

    /**
     * Znacznik ostatniej aktywności: aktualizacja bytu albo świeższy mecz w jego obrębie.
     * Przyszła data turnieju nie wyprzedza meczu, który już się toczy.
     *
     * @param  Collection<int, Tournament>  $tournaments
     * @param  Collection<int, Season>  $seasons
     * @param  Collection<int, League>  $leagues
     * @param  Collection<int, Organization>  $organizations
     * @return array{
     *     tournaments: array<int, int>,
     *     seasons: array<int, int>,
     *     leagues: array<int, int>,
     *     organizations: array<int, int>
     * }
     */
    private function activityIndex(
        Collection $tournaments,
        Collection $seasons,
        Collection $leagues,
        Collection $organizations,
    ): array {
        $seasonIds = $seasons->pluck('id')->map(fn ($id) => (int) $id)->all();
        $organizationIds = $organizations->pluck('id')->map(fn ($id) => (int) $id)->all();
        $relatedTournaments = $this->relatedTournaments($tournaments, $seasonIds, $organizationIds);
        $matchActivity = $this->latestMatchActivity($relatedTournaments->pluck('id')->map(fn ($id) => (int) $id)->all());

        $tournamentActivity = [];
        foreach ($relatedTournaments as $tournament) {
            $date = $tournament->date;
            if ($date !== null && $date->greaterThan(now()->endOfDay())) {
                $date = null;
            }
            $tournamentActivity[(int) $tournament->id] = $this->latestTimestamp([
                $tournament->updated_at,
                $tournament->created_at,
                $date,
                $matchActivity[(int) $tournament->id] ?? null,
            ]);
        }

        $seasonActivity = [];
        foreach ($seasons as $season) {
            $seasonActivity[(int) $season->id] = $this->latestTimestamp([
                $season->updated_at,
                $season->created_at,
                $season->start_date,
            ]);
        }
        foreach ($relatedTournaments as $tournament) {
            if ($tournament->season_id === null) {
                continue;
            }
            $seasonId = (int) $tournament->season_id;
            if (! array_key_exists($seasonId, $seasonActivity)) {
                continue;
            }
            $seasonActivity[$seasonId] = max(
                $seasonActivity[$seasonId],
                $tournamentActivity[(int) $tournament->id] ?? 0,
            );
        }

        $leagueIds = $leagues->pluck('id')->map(fn ($id) => (int) $id)->all();
        $leagueMatches = $this->latestLeagueMatchActivity($leagueIds);
        $leagueActivity = [];
        foreach ($leagues as $league) {
            $leagueActivity[(int) $league->id] = $this->latestTimestamp([
                $league->updated_at,
                $league->created_at,
                $leagueMatches[(int) $league->id] ?? null,
            ]);
        }

        $organizationActivity = [];
        foreach ($organizations as $organization) {
            $organizationActivity[(int) $organization->id] = $this->latestTimestamp([
                $organization->updated_at,
                $organization->created_at,
            ]);
        }
        foreach ($seasons as $season) {
            $organizationId = (int) $season->organization_id;
            if (! array_key_exists($organizationId, $organizationActivity)) {
                continue;
            }
            $organizationActivity[$organizationId] = max(
                $organizationActivity[$organizationId],
                $seasonActivity[(int) $season->id] ?? 0,
            );
        }
        foreach ($leagues as $league) {
            $organizationId = (int) $league->organization_id;
            if (! array_key_exists($organizationId, $organizationActivity)) {
                continue;
            }
            $organizationActivity[$organizationId] = max(
                $organizationActivity[$organizationId],
                $leagueActivity[(int) $league->id] ?? 0,
            );
        }
        foreach ($relatedTournaments as $tournament) {
            $organizationId = (int) ($tournament->organization_id ?? 0);
            if (! array_key_exists($organizationId, $organizationActivity)) {
                continue;
            }
            $organizationActivity[$organizationId] = max(
                $organizationActivity[$organizationId],
                $tournamentActivity[(int) $tournament->id] ?? 0,
            );
        }

        $visibleTournamentActivity = [];
        foreach ($tournaments as $tournament) {
            $visibleTournamentActivity[(int) $tournament->id] = $tournamentActivity[(int) $tournament->id] ?? 0;
        }

        return [
            'tournaments' => $visibleTournamentActivity,
            'seasons' => $seasonActivity,
            'leagues' => $leagueActivity,
            'organizations' => $organizationActivity,
        ];
    }

    /**
     * @param  Collection<int, Tournament>  $visible
     * @param  list<int>  $seasonIds
     * @param  list<int>  $organizationIds
     * @return Collection<int, Tournament>
     */
    private function relatedTournaments(Collection $visible, array $seasonIds, array $organizationIds): Collection
    {
        if ($seasonIds === [] && $organizationIds === []) {
            return $visible->unique('id')->values();
        }

        $extra = Tournament::query()
            ->where(function ($query) use ($seasonIds, $organizationIds) {
                if ($seasonIds !== []) {
                    $query->whereIn('season_id', $seasonIds);
                }
                if ($organizationIds !== []) {
                    $method = $seasonIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('organization_id', $organizationIds);
                }
            })
            ->get(['id', 'season_id', 'organization_id', 'date', 'created_at', 'updated_at']);

        return $visible
            ->concat($extra)
            ->unique('id')
            ->values();
    }

    /**
     * @param  list<int>  $tournamentIds
     * @return array<int, string>
     */
    private function latestMatchActivity(array $tournamentIds): array
    {
        if ($tournamentIds === []) {
            return [];
        }

        $merged = [];
        foreach ([Game::query(), PlayoffGame::query()] as $query) {
            $rows = $query
                ->whereIn('tournament_id', $tournamentIds)
                ->groupBy('tournament_id')
                ->select('tournament_id')
                ->selectRaw('MAX(COALESCE(finished_at, updated_at, created_at)) as activity_at')
                ->pluck('activity_at', 'tournament_id');
            foreach ($rows as $tournamentId => $activityAt) {
                $id = (int) $tournamentId;
                if (! isset($merged[$id]) || (string) $activityAt > $merged[$id]) {
                    $merged[$id] = (string) $activityAt;
                }
            }
        }

        return $merged;
    }

    /**
     * @param  list<int>  $leagueIds
     * @return array<int, string>
     */
    private function latestLeagueMatchActivity(array $leagueIds): array
    {
        if ($leagueIds === []) {
            return [];
        }

        return LeagueGame::query()
            ->join('league_seasons', 'league_seasons.id', '=', 'league_games.league_season_id')
            ->whereIn('league_seasons.league_id', $leagueIds)
            ->groupBy('league_seasons.league_id')
            ->select('league_seasons.league_id')
            ->selectRaw('MAX(COALESCE(league_games.finished_at, league_games.updated_at, league_games.created_at)) as activity_at')
            ->pluck('activity_at', 'league_id')
            ->mapWithKeys(fn ($activityAt, $leagueId) => [(int) $leagueId => (string) $activityAt])
            ->all();
    }

    /**
     * @param  list<DateTimeInterface|string|null>  $moments
     */
    private function latestTimestamp(array $moments): int
    {
        $latest = 0;
        foreach ($moments as $moment) {
            if ($moment instanceof DateTimeInterface) {
                $latest = max($latest, $moment->getTimestamp());
                continue;
            }
            if (! is_string($moment) || $moment === '') {
                continue;
            }
            $parsed = strtotime($moment);
            if ($parsed !== false) {
                $latest = max($latest, $parsed);
            }
        }

        return $latest;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $models
     * @param  array<int, int>  $activity
     * @return Collection<int, T>
     */
    private function sortByActivity(Collection $models, array $activity): Collection
    {
        return $models
            ->sort(function ($left, $right) use ($activity) {
                $byActivity = ($activity[(int) $right->id] ?? 0) <=> ($activity[(int) $left->id] ?? 0);
                if ($byActivity !== 0) {
                    return $byActivity;
                }

                return ((int) $right->id) <=> ((int) $left->id);
            })
            ->values();
    }
}
