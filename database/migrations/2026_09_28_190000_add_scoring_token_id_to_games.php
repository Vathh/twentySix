<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('scoring_token_id')->nullable()->after('status');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->unsignedBigInteger('scoring_token_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('scoring_token_id');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->dropColumn('scoring_token_id');
        });
    }
};
