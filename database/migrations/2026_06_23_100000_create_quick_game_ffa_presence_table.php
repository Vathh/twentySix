<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quick_game_ffa_presence')) {
            return;
        }

        Schema::create('quick_game_ffa_presence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ffa_session_id')
                ->constrained('quick_game_ffa_sessions')
                ->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('status', 20)->default('connected');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['ffa_session_id', 'player_id'], 'qg_ffa_presence_sess_player_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_game_ffa_presence');
    }
};
