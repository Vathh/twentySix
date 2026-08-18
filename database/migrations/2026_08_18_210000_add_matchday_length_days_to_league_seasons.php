<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->unsignedSmallInteger('matchday_length_days')->nullable()->after('rounds_each');
        });
    }

    public function down(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->dropColumn('matchday_length_days');
        });
    }
};
