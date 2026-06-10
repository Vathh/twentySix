<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quick_game_lobbies', 'legs_count')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE quick_game_lobbies MODIFY legs_count TINYINT UNSIGNED NOT NULL DEFAULT 2');
            }
        }

        if (Schema::hasColumn('quick_games', 'legs_count')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE quick_games MODIFY legs_count TINYINT UNSIGNED NOT NULL DEFAULT 2');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_game_lobbies', 'legs_count')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE quick_game_lobbies MODIFY legs_count TINYINT UNSIGNED NOT NULL DEFAULT 3');
            }
        }

        if (Schema::hasColumn('quick_games', 'legs_count')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE quick_games MODIFY legs_count TINYINT UNSIGNED NOT NULL DEFAULT 3');
            }
        }
    }
};
