<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('scoring_lock_expires_at')->nullable()->after('scoring_token_id');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->timestamp('scoring_lock_expires_at')->nullable()->after('scoring_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('scoring_lock_expires_at');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->dropColumn('scoring_lock_expires_at');
        });
    }
};
