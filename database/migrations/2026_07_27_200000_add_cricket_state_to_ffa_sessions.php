<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_game_ffa_sessions', 'cricket_state')) {
                $table->json('cricket_state')->nullable()->after('sets_won');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_ffa_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('quick_game_ffa_sessions', 'cricket_state')) {
                $table->dropColumn('cricket_state');
            }
        });
    }
};
