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
        Schema::create('playoff_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('round', 20);
            $table->string('slot', 20);
            $table->foreignId('player1_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('player2_id')->nullable()->constrained('players')->nullOnDelete();
            $table->unsignedTinyInteger('player1_score')->nullable();
            $table->unsignedTinyInteger('player2_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('winner_destination_slot', 30)->nullable();
            $table->string('status', 20)->default('SCHEDULED');
            $table->timestamps();

            $table->unique(['tournament_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playoff_games');
    }
};
