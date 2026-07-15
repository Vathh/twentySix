<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_match_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->unsignedSmallInteger('starting_score')->default(501);
            $table->unsignedTinyInteger('legs_to_win_set')->default(2);
            $table->unsignedTinyInteger('sets_to_win_match')->default(1);
            $table->string('game_type', 20)->default('x01');
            $table->timestamps();

            $table->unique(['tournament_id', 'stage']);
        });

        foreach (['games', 'playoff_games'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedSmallInteger('starting_score')->default(501)->after('status');
                $table->unsignedTinyInteger('legs_to_win_set')->default(2)->after('starting_score');
                $table->unsignedTinyInteger('sets_to_win_match')->default(1)->after('legs_to_win_set');
                $table->string('game_type', 20)->default('x01')->after('sets_to_win_match');
                $table->unsignedTinyInteger('player1_legs_in_set')->default(0)->after('player2_score');
                $table->unsignedTinyInteger('player2_legs_in_set')->default(0)->after('player1_legs_in_set');
                $table->unsignedTinyInteger('current_set_number')->default(1)->after('player2_legs_in_set');
            });
        }

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            if (Schema::hasColumn('quick_game_lobbies', 'legs_count')) {
                $table->dropColumn('legs_count');
            }
            $table->unsignedSmallInteger('starting_score')->default(501)->after('status');
            $table->unsignedTinyInteger('legs_to_win_set')->default(2)->after('starting_score');
            $table->unsignedTinyInteger('sets_to_win_match')->default(1)->after('legs_to_win_set');
        });

        Schema::table('quick_games', function (Blueprint $table) {
            if (Schema::hasColumn('quick_games', 'legs_count')) {
                $table->dropColumn('legs_count');
            }
            $table->unsignedSmallInteger('starting_score')->default(501)->after('status');
            $table->unsignedTinyInteger('legs_to_win_set')->default(2)->after('starting_score');
            $table->unsignedTinyInteger('sets_to_win_match')->default(1)->after('legs_to_win_set');
        });

        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('quick_game_ffa_sessions', 'legs_to_win')) {
                $table->unsignedTinyInteger('legs_to_win_set')->default(2)->after('lobby_id');
            }
        });

        if (Schema::hasColumn('quick_game_ffa_sessions', 'legs_to_win')) {
            DB::statement('UPDATE quick_game_ffa_sessions SET legs_to_win_set = legs_to_win');
            Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
                $table->dropColumn('legs_to_win');
            });
        }

        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('sets_to_win_match')->default(1)->after('legs_to_win_set');
            $table->unsignedTinyInteger('current_set_number')->default(1)->after('current_leg_number');
            $table->json('legs_won_in_set')->nullable()->after('player_order');
            $table->json('sets_won')->nullable()->after('legs_won_in_set');
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->dropColumn(['sets_to_win_match', 'current_set_number', 'legs_won_in_set', 'sets_won']);
            $table->renameColumn('legs_to_win_set', 'legs_to_win');
        });

        Schema::table('quick_games', function (Blueprint $table) {
            $table->dropColumn(['starting_score', 'legs_to_win_set', 'sets_to_win_match']);
            $table->unsignedTinyInteger('legs_count')->default(2)->after('status');
        });

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->dropColumn(['starting_score', 'legs_to_win_set', 'sets_to_win_match']);
            $table->unsignedTinyInteger('legs_count')->default(2)->after('status');
        });

        foreach (['games', 'playoff_games'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'starting_score',
                    'legs_to_win_set',
                    'sets_to_win_match',
                    'game_type',
                    'player1_legs_in_set',
                    'player2_legs_in_set',
                    'current_set_number',
                ]);
            });
        }

        Schema::dropIfExists('tournament_match_formats');
    }
};
