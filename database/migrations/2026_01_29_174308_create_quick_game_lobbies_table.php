<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_game_lobbies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('code', 6)->unique();
            $table->string('status', 20)->default('waiting');
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quick_game_lobby_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lobby_id')->constrained('quick_game_lobbies')->onDelete('cascade');
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('temp_player_name', 50)->nullable();
            $table->boolean('is_registered')->default(false);
            $table->boolean('is_ready')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_game_lobby_players');
        Schema::dropIfExists('quick_game_lobbies');
    }
};
