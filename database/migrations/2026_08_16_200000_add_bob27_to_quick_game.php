<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->string('bob27_mode', 16)->nullable()->after('game_type');
        });

        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->json('bob27_state')->nullable()->after('cricket_state');
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->dropColumn('bob27_mode');
        });

        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->dropColumn('bob27_state');
        });
    }
};
