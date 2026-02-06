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
        Schema::create('league_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('count_max')->default(0);
            $table->unsignedInteger('count_170_plus')->default(0);
            $table->unsignedInteger('count_qf')->default(0);
            $table->unsignedInteger('count_hf')->default(0);
            $table->unsignedInteger('best_qf')->nullable()->comment('Najniższa lotka (QF) – najmniejsza liczba lotek');
            $table->unsignedInteger('best_hf')->nullable()->comment('Najwyższy finish (HF)');
            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_player_stats');
    }
};
