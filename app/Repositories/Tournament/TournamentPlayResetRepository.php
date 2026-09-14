<?php

namespace App\Repositories\Tournament;

use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TournamentPlayResetRepository
{
    /**
     * @return list<int>
     */
    public function playerIds(int $tournamentId): array
    {
        return collect()
            ->merge(DB::table('games')->where('tournament_id', $tournamentId)->pluck('player1_id'))
            ->merge(DB::table('games')->where('tournament_id', $tournamentId)->pluck('player2_id'))
            ->merge(DB::table('playoff_games')->where('tournament_id', $tournamentId)->pluck('player1_id'))
            ->merge(DB::table('playoff_games')->where('tournament_id', $tournamentId)->pluck('player2_id'))
            ->merge(DB::table('group_standings')->where('tournament_id', $tournamentId)->pluck('player_id'))
            ->merge(DB::table('tournament_results')->where('tournament_id', $tournamentId)->pluck('player_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function groupGameIds(int $tournamentId): array
    {
        return DB::table('games')
            ->where('tournament_id', $tournamentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function playoffGameIds(int $tournamentId): array
    {
        return DB::table('playoff_games')
            ->where('tournament_id', $tournamentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Kasuje mecze, nogi, wizyty, tabele, wyniki i formaty — nie rusza uczestników.
     *
     * @param  list<int>  $groupGameIds
     * @param  list<int>  $playoffGameIds
     */
    public function wipePlayData(int $tournamentId, array $groupGameIds, array $playoffGameIds): void
    {
        $legIds = [];
        if ($groupGameIds !== [] || $playoffGameIds !== []) {
            $legIds = DB::table('game_legs')
                ->where(function ($query) use ($groupGameIds, $playoffGameIds) {
                    if ($groupGameIds !== []) {
                        $query->orWhereIn('game_id', $groupGameIds);
                    }
                    if ($playoffGameIds !== []) {
                        $query->orWhereIn('playoff_game_id', $playoffGameIds);
                    }
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($legIds !== []) {
            DB::table('game_visits')->whereIn('game_leg_id', $legIds)->delete();
            DB::table('game_leg_player_stats')->whereIn('game_leg_id', $legIds)->delete();
            DB::table('game_legs')->whereIn('id', $legIds)->delete();
        }

        if (Schema::hasTable('player_game_snapshots')) {
            if ($groupGameIds !== []) {
                DB::table('player_game_snapshots')
                    ->whereIn('sourceable_type', ['game', Game::class])
                    ->whereIn('sourceable_id', $groupGameIds)
                    ->delete();
            }
            if ($playoffGameIds !== []) {
                DB::table('player_game_snapshots')
                    ->whereIn('sourceable_type', ['playoff_game', PlayoffGame::class])
                    ->whereIn('sourceable_id', $playoffGameIds)
                    ->delete();
            }
        }

        DB::table('games')->where('tournament_id', $tournamentId)->delete();
        DB::table('playoff_games')->where('tournament_id', $tournamentId)->delete();
        DB::table('group_standings')->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_results')->where('tournament_id', $tournamentId)->delete();
        DB::table('achievements')->where('tournament_id', $tournamentId)->delete();
        DB::table('tournament_match_formats')->where('tournament_id', $tournamentId)->delete();
    }
}
