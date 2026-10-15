<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('status');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('status');
        });

        Schema::table('league_games', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('status');
        });

        $this->backfill('games', 'game_id');
        $this->backfill('playoff_games', 'playoff_game_id');
        $this->backfill('league_games', 'league_game_id');
    }

    public function down(): void
    {
        Schema::table('league_games', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });
    }

    private function backfill(string $table, string $legColumn): void
    {
        DB::statement("
            UPDATE {$table} g
            LEFT JOIN (
                SELECT {$legColumn} AS source_id, MAX(finished_at) AS finished_at
                FROM game_legs
                WHERE {$legColumn} IS NOT NULL AND finished_at IS NOT NULL
                GROUP BY {$legColumn}
            ) legs ON legs.source_id = g.id
            SET g.finished_at = COALESCE(legs.finished_at, g.updated_at, g.created_at)
            WHERE g.status = 'finished' AND g.winner_id IS NOT NULL AND g.finished_at IS NULL
        ");
    }
};
