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
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->unique()->constrained('players')->onDelete('cascade');

            // Quick game stats (z quick_game_results + achievements gdzie tournament_id null)
            $table->unsignedInteger('quick_games')->default(0);
            $table->decimal('quick_avg_three_darts', 6, 2)->nullable();
            $table->unsignedInteger('quick_highest_hf')->nullable();
            $table->unsignedInteger('quick_fastest_qf')->nullable();
            $table->unsignedInteger('quick_count_max')->default(0);
            $table->unsignedInteger('quick_count_170_plus')->default(0);
            $table->unsignedInteger('quick_count_hf')->default(0);
            $table->unsignedInteger('quick_count_qf')->default(0);

            // Tournament stats (z games/playoff_games + achievements gdzie tournament_id not null)
            $table->unsignedInteger('tournament_games')->default(0);
            $table->decimal('tournament_avg_three_darts', 6, 2)->nullable();
            $table->unsignedInteger('tournament_highest_hf')->nullable();
            $table->unsignedInteger('tournament_fastest_qf')->nullable();
            $table->unsignedInteger('tournament_count_max')->default(0);
            $table->unsignedInteger('tournament_count_170_plus')->default(0);
            $table->unsignedInteger('tournament_count_hf')->default(0);
            $table->unsignedInteger('tournament_count_qf')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
