<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('has_consolation_bracket')->default(false)->after('group_advances');
            $table->unsignedSmallInteger('consolation_bracket_size')->nullable()->after('has_consolation_bracket');
        });

        Schema::table('tournament_match_formats', function (Blueprint $table) {
            $table->string('bracket_side', 16)->default('main')->after('tournament_id');
        });

        Schema::table('tournament_match_formats', function (Blueprint $table) {
            $table->index('tournament_id', 'tournament_match_formats_tournament_id_index');
        });

        Schema::table('tournament_match_formats', function (Blueprint $table) {
            $table->dropUnique('tournament_match_formats_tournament_id_stage_unique');
            $table->unique(
                ['tournament_id', 'bracket_side', 'stage'],
                'tournament_match_formats_tournament_side_stage_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tournament_match_formats', function (Blueprint $table) {
            $table->dropUnique('tournament_match_formats_tournament_side_stage_unique');
            $table->unique(['tournament_id', 'stage'], 'tournament_match_formats_tournament_id_stage_unique');
        });

        Schema::table('tournament_match_formats', function (Blueprint $table) {
            $table->dropIndex('tournament_match_formats_tournament_id_index');
            $table->dropColumn('bracket_side');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['has_consolation_bracket', 'consolation_bracket_size']);
        });
    }
};
