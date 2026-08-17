<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->json('atc_state')->nullable()->after('bob27_state');
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            $table->dropColumn('atc_state');
        });
    }
};
