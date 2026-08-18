<?php

namespace App\Repositories\League;

use App\Enums\LeagueGameStatus;
use App\Enums\LeagueWalkoverType;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\League\LeagueSeasonDivision;
use App\Models\League\LeagueSeasonMatchday;
use App\Models\League\LeagueSeasonParticipant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class LeagueSeasonRepository
{
    public function find(int $seasonId): LeagueSeason
    {
        return LeagueSeason::query()->findOrFail($seasonId);
    }

    public function findWithGraph(int $seasonId): LeagueSeason
    {
        return LeagueSeason::query()
            ->with([
                'league.organization.admins',
                'league.divisions',
                'divisions.participants.player',
                'participants.player',
                'matchdays',
                'games.player1',
                'games.player2',
                'games.winner',
            ])
            ->findOrFail($seasonId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): LeagueSeason
    {
        return LeagueSeason::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int $seasonId, array $payload): void
    {
        $season = LeagueSeason::query()->findOrFail($seasonId);
        $season->fill($payload);
        $season->save();
    }

    /**
     * @param  list<array<string, mixed>>  $divisions
     * @param  list<array{league_season_division_position: int, player_id: int}>  $participants
     */
    public function snapshotStructure(LeagueSeason $season, array $divisions, array $participants): void
    {
        DB::transaction(function () use ($season, $divisions, $participants) {
            $positionToId = [];
            foreach ($divisions as $division) {
                $created = $season->divisions()->create($division);
                $positionToId[(int) $division['position']] = $created->id;
            }

            foreach ($participants as $participant) {
                $season->participants()->create([
                    'league_season_division_id' => $positionToId[$participant['league_season_division_position']],
                    'player_id' => $participant['player_id'],
                ]);
            }
        });
    }

    /**
     * @param  list<array{round_number: int, window_start: CarbonInterface, window_end: CarbonInterface}>  $matchdays
     * @return array<int, int> round_number => id
     */
    public function createMatchdays(LeagueSeason $season, array $matchdays): array
    {
        $ids = [];
        foreach ($matchdays as $matchday) {
            $created = $season->matchdays()->create($matchday);
            $ids[(int) $matchday['round_number']] = $created->id;
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $games
     */
    public function createGames(array $games): void
    {
        foreach ($games as $game) {
            LeagueGame::query()->create($game);
        }
    }

    public function findGame(int $gameId): LeagueGame
    {
        return LeagueGame::query()
            ->with(['season.league.organization.admins', 'player1', 'player2', 'matchday'])
            ->findOrFail($gameId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateGame(int $gameId, array $payload): void
    {
        $game = LeagueGame::query()->findOrFail($gameId);
        $game->fill($payload);
        $game->save();
    }

    public function voidGamesForPlayer(int $seasonId, int $playerId): void
    {
        LeagueGame::query()
            ->where('league_season_id', $seasonId)
            ->where(function ($query) use ($playerId) {
                $query->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->update([
                'status' => LeagueGameStatus::VOIDED,
                'player1_score' => null,
                'player2_score' => null,
                'winner_id' => null,
                'walkover_type' => LeagueWalkoverType::NONE,
            ]);
    }

    public function markWithdrawn(int $seasonId, int $playerId, CarbonInterface $at): void
    {
        LeagueSeasonParticipant::query()
            ->where('league_season_id', $seasonId)
            ->where('player_id', $playerId)
            ->update(['withdrawn_at' => $at]);
    }

    public function extendGameDeadline(int $gameId, CarbonInterface $deadline): void
    {
        $game = LeagueGame::query()->findOrFail($gameId);
        $game->deadline_at = $deadline;
        $game->save();

        if ($game->league_season_matchday_id) {
            LeagueSeasonMatchday::query()
                ->whereKey($game->league_season_matchday_id)
                ->update(['window_end' => $deadline]);
        }
    }

    public function extendMatchday(int $matchdayId, CarbonInterface $windowEnd): void
    {
        $matchday = LeagueSeasonMatchday::query()->findOrFail($matchdayId);
        $matchday->window_end = $windowEnd;
        $matchday->save();

        LeagueGame::query()
            ->where('league_season_matchday_id', $matchdayId)
            ->where('status', LeagueGameStatus::SCHEDULED->value)
            ->update(['deadline_at' => $windowEnd]);
    }

    public function seasonDivision(int $id): LeagueSeasonDivision
    {
        return LeagueSeasonDivision::query()->findOrFail($id);
    }

    /**
     * Skład z chwili startu (także osoby, które potem zrezygnowały).
     *
     * @return array<int, list<int>> live division id => player ids
     */
    public function snapshotRosterByLiveDivision(LeagueSeason $season): array
    {
        $roster = [];
        foreach ($season->divisions as $division) {
            $liveId = $division->league_division_id;
            if ($liveId === null) {
                continue;
            }
            $roster[$liveId] = $division->participants
                ->pluck('player_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $roster;
    }

    public function delete(int $seasonId): void
    {
        DB::transaction(function () use ($seasonId) {
            LeagueGame::query()->where('league_season_id', $seasonId)->delete();
            LeagueSeasonMatchday::query()->where('league_season_id', $seasonId)->delete();
            LeagueSeasonParticipant::query()->where('league_season_id', $seasonId)->delete();
            LeagueSeasonDivision::query()->where('league_season_id', $seasonId)->delete();
            LeagueSeason::query()->whereKey($seasonId)->delete();
        });
    }
}
