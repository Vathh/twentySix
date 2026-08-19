<?php

use App\Enums\MatchWinMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->boolean('allows_draws')->default(false)->after('rounds_each');
            $table->string('win_mode', 16)->default(MatchWinMode::FIRST_TO->value)->after('allows_draws');
            $table->unsignedTinyInteger('win_length')->default(2)->after('win_mode');
        });

        Schema::table('league_games', function (Blueprint $table) {
            $table->string('win_mode', 16)->default(MatchWinMode::FIRST_TO->value)->after('game_type');
            $table->unsignedTinyInteger('win_length')->nullable()->after('win_mode');
            $table->foreignId('lobby_host_player_id')->nullable()->after('win_length')->constrained('players')->nullOnDelete();
            $table->timestamp('opponent_accepted_at')->nullable()->after('lobby_host_player_id');
            $table->foreignId('scoring_host_player_id')->nullable()->after('opponent_accepted_at')->constrained('players')->nullOnDelete();
        });

        Schema::table('game_legs', function (Blueprint $table) {
            $table->foreignId('league_game_id')->nullable()->after('quick_game_id')->constrained('league_games')->nullOnDelete();
            $table->index(['league_game_id', 'leg_number']);
        });

        DB::statement("ALTER TABLE league_games MODIFY COLUMN status ENUM('scheduled', 'lobby', 'in_progress', 'finished', 'voided') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE league_games MODIFY COLUMN status ENUM('scheduled', 'finished', 'voided') NOT NULL DEFAULT 'scheduled'");

        Schema::table('game_legs', function (Blueprint $table) {
            $table->dropForeign(['league_game_id']);
            $table->dropIndex(['league_game_id', 'leg_number']);
            $table->dropColumn('league_game_id');
        });

        Schema::table('league_games', function (Blueprint $table) {
            $table->dropForeign(['lobby_host_player_id']);
            $table->dropForeign(['scoring_host_player_id']);
            $table->dropColumn([
                'win_mode',
                'win_length',
                'lobby_host_player_id',
                'opponent_accepted_at',
                'scoring_host_player_id',
            ]);
        });

        Schema::table('league_seasons', function (Blueprint $table) {
            $table->dropColumn(['allows_draws', 'win_mode', 'win_length']);
        });
    }
};
