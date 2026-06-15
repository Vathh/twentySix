<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_game_results', function (Blueprint $table) {
            if (! Schema::hasColumn('quick_game_results', 'average')) {
                $table->decimal('average', 8, 2)->nullable()->after('place');
            }
            if (! Schema::hasColumn('quick_game_results', 'darts_thrown')) {
                $table->unsignedSmallInteger('darts_thrown')->nullable()->after('average');
            }
            if (! Schema::hasColumn('quick_game_results', 'points_earned')) {
                $table->unsignedSmallInteger('points_earned')->nullable()->after('darts_thrown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_game_results', function (Blueprint $table) {
            if (Schema::hasColumn('quick_game_results', 'points_earned')) {
                $table->dropColumn('points_earned');
            }
            if (Schema::hasColumn('quick_game_results', 'darts_thrown')) {
                $table->dropColumn('darts_thrown');
            }
            if (Schema::hasColumn('quick_game_results', 'average')) {
                $table->dropColumn('average');
            }
        });
    }
};
