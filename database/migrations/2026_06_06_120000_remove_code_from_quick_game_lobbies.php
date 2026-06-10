<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quick_game_lobbies', 'code')) {
            return;
        }

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_game_lobbies', 'code')) {
            return;
        }

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->string('code', 6)->unique()->after('host_id');
        });
    }
};
