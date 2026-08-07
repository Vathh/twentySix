<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->foreignId('rematch_lobby_id')
                ->nullable()
                ->after('quick_game_id')
                ->constrained('quick_game_lobbies')
                ->nullOnDelete();
        });

        Schema::create('quick_game_lobby_rematch_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_lobby_id')->constrained('quick_game_lobbies')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source_lobby_id', 'player_id'], 'qg_lobby_rematch_intents_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_game_lobby_rematch_intents');

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rematch_lobby_id');
        });
    }
};
