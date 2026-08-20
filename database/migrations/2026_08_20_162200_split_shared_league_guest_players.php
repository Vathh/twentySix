<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $shared = DB::table('players')
            ->whereNull('user_id')
            ->whereNotNull('league_id')
            ->where(function ($query) {
                $query->whereNotNull('organization_id')
                    ->orWhereNotNull('season_id');
            })
            ->get();

        foreach ($shared as $player) {
            $leagueId = (int) $player->league_id;
            $newPlayerId = DB::table('players')->insertGetId([
                'name' => $player->name,
                'description' => $player->description,
                'user_id' => null,
                'organization_id' => null,
                'season_id' => null,
                'league_id' => $leagueId,
                'created_at' => $player->created_at,
                'updated_at' => now(),
            ]);

            DB::table('league_division_members')
                ->where('league_id', $leagueId)
                ->where('player_id', $player->id)
                ->update(['player_id' => $newPlayerId]);

            $seasonIds = DB::table('league_seasons')
                ->where('league_id', $leagueId)
                ->pluck('id');

            if ($seasonIds->isNotEmpty()) {
                DB::table('league_season_participants')
                    ->whereIn('league_season_id', $seasonIds)
                    ->where('player_id', $player->id)
                    ->update(['player_id' => $newPlayerId]);

                foreach (['player1_id', 'player2_id', 'winner_id', 'lobby_host_player_id', 'scoring_host_player_id'] as $column) {
                    DB::table('league_games')
                        ->whereIn('league_season_id', $seasonIds)
                        ->where($column, $player->id)
                        ->update([$column => $newPlayerId]);
                }
            }

            DB::table('players')
                ->where('id', $player->id)
                ->update([
                    'league_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Rozdzielenie puli jest jednokierunkowe — goście ligowi i organizacyjni
        // mają już osobne rekordy i nie da się ich bezpiecznie scalić.
    }
};
