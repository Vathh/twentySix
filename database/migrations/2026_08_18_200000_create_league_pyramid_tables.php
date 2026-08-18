<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('league_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name');
            $table->unsignedTinyInteger('capacity');
            $table->unsignedSmallInteger('starting_score')->default(501);
            $table->unsignedTinyInteger('legs_to_win_set')->default(2);
            $table->unsignedTinyInteger('sets_to_win_match')->default(1);
            $table->string('game_type', 32)->default('x01');
            $table->unsignedTinyInteger('promote_direct')->default(0);
            $table->unsignedTinyInteger('promote_playoff')->default(0);
            $table->timestamps();

            $table->unique(['league_id', 'position']);
        });

        Schema::create('league_division_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('league_division_id')->constrained('league_divisions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
            $table->unique(['league_division_id', 'player_id']);
        });

        Schema::create('league_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['draft', 'in_progress', 'playoffs', 'finished'])->default('draft');
            $table->enum('calendar_mode', ['matchdays', 'deadline']);
            $table->unsignedTinyInteger('rounds_each')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedInteger('random_seed')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('league_season_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_season_id')->constrained('league_seasons')->cascadeOnDelete();
            $table->foreignId('league_division_id')->nullable()->constrained('league_divisions')->nullOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name');
            $table->unsignedTinyInteger('capacity');
            $table->unsignedSmallInteger('starting_score');
            $table->unsignedTinyInteger('legs_to_win_set');
            $table->unsignedTinyInteger('sets_to_win_match');
            $table->string('game_type', 32)->default('x01');
            $table->unsignedTinyInteger('promote_direct')->default(0);
            $table->unsignedTinyInteger('promote_playoff')->default(0);
            $table->timestamps();

            $table->unique(['league_season_id', 'position']);
        });

        Schema::create('league_season_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_season_id')->constrained('league_seasons')->cascadeOnDelete();
            $table->foreignId('league_season_division_id')->constrained('league_season_divisions')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['league_season_id', 'player_id'], 'league_season_player_unique');
        });

        Schema::create('league_season_matchdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_season_id')->constrained('league_seasons')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->dateTime('window_start');
            $table->dateTime('window_end');
            $table->timestamps();

            $table->unique(['league_season_id', 'round_number']);
        });

        Schema::create('league_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_season_id')->constrained('league_seasons')->cascadeOnDelete();
            $table->foreignId('league_season_division_id')->nullable()->constrained('league_season_divisions')->nullOnDelete();
            $table->foreignId('higher_season_division_id')->nullable()->constrained('league_season_divisions')->nullOnDelete();
            $table->foreignId('lower_season_division_id')->nullable()->constrained('league_season_divisions')->nullOnDelete();
            $table->foreignId('league_season_matchday_id')->nullable()->constrained('league_season_matchdays')->nullOnDelete();
            $table->enum('purpose', ['regular', 'promotion_playoff', 'tiebreaker']);
            $table->foreignId('player1_id')->constrained('players');
            $table->foreignId('player2_id')->constrained('players');
            $table->unsignedTinyInteger('player1_score')->nullable();
            $table->unsignedTinyInteger('player2_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('players');
            $table->enum('status', ['scheduled', 'finished', 'voided'])->default('scheduled');
            $table->enum('walkover_type', ['none', 'single', 'both'])->default('none');
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedSmallInteger('starting_score');
            $table->unsignedTinyInteger('legs_to_win_set');
            $table->unsignedTinyInteger('sets_to_win_match');
            $table->string('game_type', 32)->default('x01');
            $table->string('tie_group_key')->nullable();
            $table->unsignedTinyInteger('bracket_round')->nullable();
            $table->boolean('is_third_place')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_games');
        Schema::dropIfExists('league_season_matchdays');
        Schema::dropIfExists('league_season_participants');
        Schema::dropIfExists('league_season_divisions');
        Schema::dropIfExists('league_seasons');
        Schema::dropIfExists('league_division_members');
        Schema::dropIfExists('league_divisions');
        Schema::dropIfExists('leagues');
    }
};
