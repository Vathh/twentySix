<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('group_standings', 'matches_played')) {
            Schema::table('group_standings', function (Blueprint $table) {
                $table->renameColumn('matches_played', 'games_played');
                $table->renameColumn('matches_won', 'games_won');
                $table->renameColumn('matches_lost', 'games_lost');
            });
        }

        if (Schema::hasTable('player_stats') && Schema::hasColumn('player_stats', 'quick_matches')) {
            Schema::table('player_stats', function (Blueprint $table) {
                $table->renameColumn('quick_matches', 'quick_games');
                $table->renameColumn('tournament_matches', 'tournament_games');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_standings', 'games_played')) {
            Schema::table('group_standings', function (Blueprint $table) {
                $table->renameColumn('games_played', 'matches_played');
                $table->renameColumn('games_won', 'matches_won');
                $table->renameColumn('games_lost', 'matches_lost');
            });
        }

        if (Schema::hasTable('player_stats') && Schema::hasColumn('player_stats', 'quick_games')) {
            Schema::table('player_stats', function (Blueprint $table) {
                $table->renameColumn('quick_games', 'quick_matches');
                $table->renameColumn('tournament_games', 'tournament_matches');
            });
        }
    }
};
