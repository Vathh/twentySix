<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_id')->constrained('quick_game_lobbies')->cascadeOnDelete();
            $table->unsignedTinyInteger('legs_to_win')->default(2);
            $table->string('game_type', 20)->default('501');
            $table->string('scoring_mode', 20)->default('each_own');
            $table->unsignedSmallInteger('starting_score')->default(501);
            $table->string('status', 20)->default('in_progress');
            $table->json('player_order');
            $table->unsignedTinyInteger('leg_opener_index')->default(0);
            $table->unsignedTinyInteger('current_player_index')->default(0);
            $table->unsignedTinyInteger('current_leg_number')->default(1);
            $table->unsignedInteger('state_version')->default(1);
            $table->foreignId('quick_game_id')->nullable()->constrained('quick_games')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique('lobby_id');
        });

        Schema::create('quick_game_ffa_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ffa_session_id')->constrained('quick_game_ffa_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('leg_number');
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedInteger('visit_number');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('remaining_before');
            $table->unsignedSmallInteger('remaining_after');
            $table->unsignedTinyInteger('darts_in_visit')->default(3);
            $table->boolean('closed_leg')->default(false);
            $table->boolean('bust')->default(false);
            $table->boolean('is_voided')->default(false);
            $table->uuid('client_visit_id')->unique();
            $table->timestamps();

            $table->index(
                ['ffa_session_id', 'leg_number', 'is_voided', 'visit_number'],
                'qg_ffa_visits_sess_leg_idx',
            );
        });

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_game_lobbies', 'ffa_session_id')) {
                $table->foreignId('ffa_session_id')
                    ->nullable()
                    ->after('quick_game_id')
                    ->constrained('quick_game_ffa_sessions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            if (Schema::hasColumn('quick_game_lobbies', 'ffa_session_id')) {
                $table->dropConstrainedForeignId('ffa_session_id');
            }
        });

        Schema::dropIfExists('quick_game_ffa_visits');
        Schema::dropIfExists('quick_game_ffa_sessions');
    }
};
