<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('grand_final_mode', 16)->nullable()->after('format');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->string('bracket_side', 16)->default('main')->after('tournament_id');
            $table->string('loser_destination_slot', 40)->nullable()->after('winner_destination_slot');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('grand_final_mode');
        });

        Schema::table('playoff_games', function (Blueprint $table) {
            $table->dropColumn(['bracket_side', 'loser_destination_slot']);
        });
    }
};
