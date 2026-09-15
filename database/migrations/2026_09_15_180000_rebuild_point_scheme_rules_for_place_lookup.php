<?php

use Database\Seeders\PointSchemeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punktacja sezonowa: kubełki miejsca (place_from/to) + format SE/DE.
 * Historyczne wyniki i stare schematy kasujemy — liczby z design_point_schemes.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tournament_results')->delete();
        DB::table('tournaments')->update(['point_scheme_id' => null]);
        DB::table('point_scheme_rules')->delete();
        DB::table('point_schemes')->delete();

        Schema::dropIfExists('point_scheme_rules');
        Schema::create('point_scheme_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('point_scheme_id');
            $table->string('format', 8);
            $table->unsignedSmallInteger('place_from');
            $table->unsignedSmallInteger('place_to');
            $table->unsignedSmallInteger('points');
            $table->timestamps();
            $table->unique(['point_scheme_id', 'format', 'place_from', 'place_to'], 'unique_point_scheme_rules');
        });

        (new PointSchemeSeeder)->replaceAll();
    }

    public function down(): void
    {
        DB::table('tournament_results')->delete();
        DB::table('tournaments')->update(['point_scheme_id' => null]);
        DB::table('point_scheme_rules')->delete();
        DB::table('point_schemes')->delete();

        Schema::dropIfExists('point_scheme_rules');
        Schema::create('point_scheme_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('point_scheme_id');
            $table->string('elimination_stage');
            $table->unsignedSmallInteger('place')->nullable();
            $table->unsignedSmallInteger('points');
            $table->timestamps();
            $table->unique(['point_scheme_id', 'elimination_stage', 'place'], 'unique_point_scheme_rules');
        });
    }
};
