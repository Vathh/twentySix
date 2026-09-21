<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $formatTables = [
            'quick_game_lobbies',
            'quick_game_ffa_sessions',
            'quick_games',
            'tournament_match_formats',
            'games',
            'playoff_games',
            'league_divisions',
            'league_season_divisions',
            'league_games',
        ];

        foreach ($formatTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('dart_limit')->nullable();
                $table->unsignedSmallInteger('loss_threshold')->nullable();
            });
        }

        Schema::table('game_legs', function (Blueprint $table) {
            $table->string('close_reason', 32)->nullable();
        });

        Schema::table('quick_game_ffa_visits', function (Blueprint $table) {
            $table->string('close_reason', 32)->nullable();
        });

        Schema::table('game_visits', function (Blueprint $table) {
            $table->string('close_reason', 32)->nullable();
        });
    }

    public function down(): void
    {
        $formatTables = [
            'quick_game_lobbies',
            'quick_game_ffa_sessions',
            'quick_games',
            'tournament_match_formats',
            'games',
            'playoff_games',
            'league_divisions',
            'league_season_divisions',
            'league_games',
        ];

        foreach ($formatTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['dart_limit', 'loss_threshold']);
            });
        }

        Schema::table('game_legs', function (Blueprint $table) {
            $table->dropColumn('close_reason');
        });

        Schema::table('quick_game_ffa_visits', function (Blueprint $table) {
            $table->dropColumn('close_reason');
        });

        Schema::table('game_visits', function (Blueprint $table) {
            $table->dropColumn('close_reason');
        });
    }
};
