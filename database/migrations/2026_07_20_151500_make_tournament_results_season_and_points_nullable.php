<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_results', function (Blueprint $table) {
            $table->unsignedBigInteger('season_id')->nullable()->change();
            $table->smallInteger('points')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_results', function (Blueprint $table) {
            $table->unsignedBigInteger('season_id')->nullable(false)->change();
            $table->smallInteger('points')->nullable(false)->change();
        });
    }
};
