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
        Schema::create('group_standings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')->constrained('tournaments')->onDelete('cascade');

            $table->unsignedInteger('group_number');

            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');

            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_won')->default(0);
            $table->unsignedInteger('games_lost')->default(0);
            $table->unsignedInteger('legs_won')->default(0);
            $table->unsignedInteger('legs_lost')->default(0);
            $table->integer('points')->default(0);

            $table->integer('legs_difference')->virtualAs('legs_won - legs_lost');

            $table->timestamps();

            $table->unique(['tournament_id', 'group_number', 'player_id'], 'unique_standing_per_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_standings');
    }
};
