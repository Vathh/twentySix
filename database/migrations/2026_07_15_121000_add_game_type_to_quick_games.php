<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_games', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_games', 'game_type')) {
                $table->string('game_type', 20)->default('x01')->after('sets_to_win_match');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_games', function (Blueprint $table) {
            if (Schema::hasColumn('quick_games', 'game_type')) {
                $table->dropColumn('game_type');
            }
        });
    }
};
