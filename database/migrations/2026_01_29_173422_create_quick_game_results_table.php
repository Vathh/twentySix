<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quick_game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_game_id')->constrained('quick_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedTinyInteger('place')->default(0);
            $table->decimal('average', 8, 2)->nullable();
            $table->unsignedSmallInteger('darts_thrown')->nullable();
            $table->unsignedSmallInteger('points_earned')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quick_game_results');
    }
};
