<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->enum('matchday_planning', ['fixed_length', 'equal_span'])
                ->nullable()
                ->after('matchday_length_days');
        });

        DB::table('league_seasons')
            ->where('calendar_mode', 'matchdays')
            ->whereNotNull('matchday_length_days')
            ->update(['matchday_planning' => 'fixed_length']);

        DB::table('league_seasons')
            ->where('calendar_mode', 'matchdays')
            ->whereNull('matchday_length_days')
            ->update(['matchday_planning' => 'equal_span']);
    }

    public function down(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->dropColumn('matchday_planning');
        });
    }
};
