<?php

namespace App\Repositories\Platform;

use App\Enums\TournamentStatus;
use App\Enums\GameStatus;
use App\Models\League\League;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameLobby;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PlatformAdminRepository
{
    /**
     * @return array{
     *     usersTotal: int,
     *     usersVerified: int,
     *     usersCanCreateLeagues: int,
     *     usersRegisteredToday: int,
     *     usersRegisteredLast7Days: int,
     *     leaguesTotal: int,
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
            'usersCanCreateLeagues' => User::query()->where('can_create_leagues', true)->count(),
            'usersRegisteredToday' => User::query()->whereDate('created_at', $now->toDateString())->count(),
            'usersRegisteredLast7Days' => User::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'leaguesTotal' => League::query()->count(),
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

    public function setCanCreateLeagues(User $user, bool $enabled): void
    {
        $user->forceFill(['can_create_leagues' => $enabled])->save();
    }
}
