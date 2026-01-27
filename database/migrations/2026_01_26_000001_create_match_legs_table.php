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
        Schema::create('match_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->foreignId('playoff_game_id')->nullable()->constrained('playoffgames')->nullOnDelete();
            $table->foreignId('quick_game_id')->nullable()->constrained('quick_games')->nullOnDelete();
            $table->unsignedInteger('leg_number'); // Numer lega w meczu (1, 2, 3, ...)
            $table->unsignedInteger('player1_score')->default(0); // Punkty gracza 1 w tym legu
            $table->unsignedInteger('player2_score')->default(0); // Punkty gracza 2 w tym legu
            $table->foreignId('winner_id')->nullable()->constrained('players')->nullOnDelete();
            $table->unsignedInteger('player1_average')->nullable(); // Średnia gracza 1 w tym legu
            $table->unsignedInteger('player2_average')->nullable(); // Średnia gracza 2 w tym legu
            $table->unsignedInteger('player1_darts_thrown')->nullable(); // Liczba rzutów gracza 1
            $table->unsignedInteger('player2_darts_thrown')->nullable(); // Liczba rzutów gracza 2
            $table->unsignedInteger('checkout_score')->nullable(); // Wartość checkout (jeśli był)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Jeden mecz może mieć wiele legów, ale każdy leg należy do jednego meczu
            $table->index(['game_id', 'leg_number']);
            $table->index(['playoff_game_id', 'leg_number']);
            $table->index(['quick_game_id', 'leg_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_legs');
    }
};
