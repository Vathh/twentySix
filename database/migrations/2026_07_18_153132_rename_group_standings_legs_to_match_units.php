<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Starsze bazy miały legs_won / legs_lost; kod i create migration używają match_units_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_standings')) {
            return;
        }

        if (
            Schema::hasColumn('group_standings', 'legs_won')
            && ! Schema::hasColumn('group_standings', 'match_units_won')
        ) {
            if (Schema::hasColumn('group_standings', 'legs_difference')) {
                Schema::table('group_standings', function (Blueprint $table) {
                    $table->dropColumn('legs_difference');
                });
            }

            Schema::table('group_standings', function (Blueprint $table) {
                $table->renameColumn('legs_won', 'match_units_won');
                $table->renameColumn('legs_lost', 'match_units_lost');
            });

            Schema::table('group_standings', function (Blueprint $table) {
                $table->integer('match_units_difference')
                    ->virtualAs('match_units_won - match_units_lost');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('group_standings')) {
            return;
        }

        if (
            Schema::hasColumn('group_standings', 'match_units_won')
            && ! Schema::hasColumn('group_standings', 'legs_won')
        ) {
            if (Schema::hasColumn('group_standings', 'match_units_difference')) {
                Schema::table('group_standings', function (Blueprint $table) {
                    $table->dropColumn('match_units_difference');
                });
            }

            Schema::table('group_standings', function (Blueprint $table) {
                $table->renameColumn('match_units_won', 'legs_won');
                $table->renameColumn('match_units_lost', 'legs_lost');
            });

            Schema::table('group_standings', function (Blueprint $table) {
                $table->integer('legs_difference')->virtualAs('legs_won - legs_lost');
            });
        }
    }
};
