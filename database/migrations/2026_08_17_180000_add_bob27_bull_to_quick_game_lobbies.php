<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->string('bob27_bull', 16)->nullable()->after('bob27_mode');
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->dropColumn('bob27_bull');
        });
    }
};
