<?php

namespace App\Repositories\Platform;

use App\Enums\TournamentStatus;
use App\Enums\GameStatus;
use App\Models\Organization\Organization;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameLobby;
use App\Models\QuickGame\QuickGameLobbyPlayer;
use App\Models\QuickGame\QuickGameResult;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformAdminRepository
{
    /**
     * @return array{
     *     usersTotal: int,
     *     usersVerified: int,
     *     usersCanCreateOrganizations: int,
     *     usersRegisteredToday: int,
     *     usersRegisteredLast7Days: int,
     *     organizationsTotal: int,
     *     seasonsTotal: int,
     *     tournamentsTotal: int,
     *     tournamentsByStatus: array<string, int>,
     *     quickGameLobbiesTotal: int,
     *     quickGameLobbiesWaiting: int,
     *     quickGameLobbiesInProgress: int,
     *     quickGamesFinished: int,
     *     ffaSessionsInProgress: int,
     *     ffaSessionsFinished: int
     * }
     */
    public function dashboardStats(): array
    {
        $now = Carbon::now();

        $tournamentsByStatus = [
            TournamentStatus::CREATED->value => 0,
            TournamentStatus::GROUP->value => 0,
            TournamentStatus::PLAYOFF->value => 0,
            TournamentStatus::FINISHED->value => 0,
        ];

        foreach (
            Tournament::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status') as $status => $count
        ) {
            $key = $status instanceof TournamentStatus ? $status->value : (string) $status;
            $tournamentsByStatus[$key] = (int) $count;
        }

        return [
            'usersTotal' => User::query()->count(),
            'usersVerified' => User::query()->whereNotNull('email_verified_at')->count(),
            'usersCanCreateOrganizations' => User::query()->where('can_create_organizations', true)->count(),
            'usersRegisteredToday' => User::query()->whereDate('created_at', $now->toDateString())->count(),
            'usersRegisteredLast7Days' => User::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'organizationsTotal' => Organization::query()->count(),
            'seasonsTotal' => Season::query()->count(),
            'tournamentsTotal' => Tournament::query()->count(),
            'tournamentsByStatus' => $tournamentsByStatus,
            'quickGameLobbiesTotal' => QuickGameLobby::query()->count(),
            'quickGameLobbiesWaiting' => QuickGameLobby::query()->where('status', 'waiting')->count(),
            'quickGameLobbiesInProgress' => QuickGameLobby::query()->where('status', 'in_progress')->count(),
            'quickGamesFinished' => QuickGame::query()->where('status', GameStatus::FINISHED)->count(),
            'ffaSessionsInProgress' => QuickGameFfaSession::query()
                ->where('status', QuickGameFfaSession::STATUS_IN_PROGRESS)
                ->count(),
            'ffaSessionsFinished' => QuickGameFfaSession::query()
                ->where('status', QuickGameFfaSession::STATUS_FINISHED)
                ->count(),
        ];
    }

    public function paginateUsers(?string $search, int $perPage = 30): LengthAwarePaginator
    {
        $query = User::query()
            ->with('player')
            ->orderByDesc('id');

        $term = trim((string) $search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhereHas('player', fn ($pq) => $pq->where('name', 'like', $like));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function setCanCreateOrganizations(User $user, bool $enabled): void
    {
        $user->forceFill(['can_create_organizations' => $enabled])->save();
    }

    public function setBanned(User $user, bool $banned): void
    {
        $user->forceFill([
            'banned_at' => $banned ? now() : null,
        ])->save();
    }

    /**
     * @return array{
     *     organizationsAdmin: list<array{id: int, name: string}>,
     *     organizationsMember: list<array{id: int, name: string}>,
     *     friendsCount: int,
     *     friends: list<string>,
     *     tournamentResultsCount: int,
     *     tournamentResults: list<array{tournament: string, place: int|null, points: int|null, date: string|null}>,
     *     lobbiesHostedCount: int,
     *     lobbiesAsPlayerCount: int,
     *     quickGameResultsCount: int,
     *     lastApiUsedAt: string|null,
     *     lastTokenCreatedAt: string|null,
     *     activeTokensCount: int
     * }
     */
    public function userActivity(User $user): array
    {
        $user->loadMissing('player');
        $playerId = $user->player?->id;

        $organizationsAdmin = $user->adminOrganizations()
            ->orderBy('name')
            ->get(['organizations.id', 'organizations.name'])
            ->map(fn ($organization) => ['id' => (int) $organization->id, 'name' => (string) $organization->name])
            ->all();

        $organizationsMember = $user->relatedOrganizations()
            ->orderBy('name')
            ->get(['organizations.id', 'organizations.name'])
            ->map(fn ($organization) => ['id' => (int) $organization->id, 'name' => (string) $organization->name])
            ->all();

        $friendIds = DB::table('friendships')
            ->where('user_id', $user->id)
            ->pluck('friend_id')
            ->merge(
                DB::table('friendships')->where('friend_id', $user->id)->pluck('user_id')
            )
            ->unique()
            ->values();

        $friendNames = [];
        if ($friendIds->isNotEmpty()) {
            $friendNames = User::query()
                ->with('player')
                ->whereIn('id', $friendIds)
                ->get()
                ->map(fn (User $friend) => $friend->player?->name ?? $friend->email)
                ->sort()
                ->values()
                ->take(30)
                ->all();
        }

        $tournamentResults = [];
        $tournamentResultsCount = 0;
        $lobbiesAsPlayerCount = 0;
        $quickGameResultsCount = 0;

        if ($playerId !== null) {
            $tournamentResultsCount = TournamentResult::query()
                ->where('player_id', $playerId)
                ->count();

            $tournamentResults = TournamentResult::query()
                ->with('tournament:id,name')
                ->where('player_id', $playerId)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (TournamentResult $row) => [
                    'tournament' => (string) ($row->tournament?->name ?? '—'),
                    'place' => $row->place !== null ? (int) $row->place : null,
                    'points' => $row->points !== null ? (int) $row->points : null,
                    'date' => $row->created_at?->format('Y-m-d H:i'),
                ])
                ->all();

            $lobbiesAsPlayerCount = QuickGameLobbyPlayer::query()
                ->where('player_id', $playerId)
                ->count();

            $quickGameResultsCount = QuickGameResult::query()
                ->where('player_id', $playerId)
                ->count();
        }

        $lastApiUsedAt = $user->tokens()->max('last_used_at');
        $lastTokenCreatedAt = $user->tokens()->max('created_at');

        return [
            'organizationsAdmin' => $organizationsAdmin,
            'organizationsMember' => $organizationsMember,
            'friendsCount' => $friendIds->count(),
            'friends' => $friendNames,
            'tournamentResultsCount' => $tournamentResultsCount,
            'tournamentResults' => $tournamentResults,
            'lobbiesHostedCount' => QuickGameLobby::query()->where('host_id', $user->id)->count(),
            'lobbiesAsPlayerCount' => $lobbiesAsPlayerCount,
            'quickGameResultsCount' => $quickGameResultsCount,
            'lastApiUsedAt' => $lastApiUsedAt
                ? Carbon::parse($lastApiUsedAt)->format('Y-m-d H:i')
                : null,
            'lastTokenCreatedAt' => $lastTokenCreatedAt
                ? Carbon::parse($lastTokenCreatedAt)->format('Y-m-d H:i')
                : null,
            'activeTokensCount' => $user->tokens()->count(),
        ];
    }
}
