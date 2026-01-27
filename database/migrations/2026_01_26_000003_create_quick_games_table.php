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
        Schema::create('quick_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player1_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('player2_id')->constrained('players')->onDelete('cascade');
            $table->unsignedInteger('player1_score')->nullable();
            $table->unsignedInteger('player2_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('players')->nullOnDelete();
            $table->enum('status', ['scheduled', 'in_progress', 'finished'])->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quick_games');
    }
};
