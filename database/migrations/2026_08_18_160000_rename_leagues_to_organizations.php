<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leagues')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropForeign(['league_id']);
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropForeign(['league_id']);
            });
            Schema::table('league_user', function (Blueprint $table) {
                $table->dropForeign(['league_id']);
            });
            Schema::table('league_user_admin', function (Blueprint $table) {
                $table->dropForeign(['league_id']);
            });

            Schema::rename('leagues', 'organizations');
            Schema::rename('league_user', 'organization_user');
            Schema::rename('league_user_admin', 'organization_user_admin');

            Schema::table('players', function (Blueprint $table) {
                $table->renameColumn('league_id', 'organization_id');
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->renameColumn('league_id', 'organization_id');
            });
            Schema::table('organization_user', function (Blueprint $table) {
                $table->renameColumn('league_id', 'organization_id');
            });
            Schema::table('organization_user_admin', function (Blueprint $table) {
                $table->renameColumn('league_id', 'organization_id');
            });

            Schema::table('players', function (Blueprint $table) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            });
            Schema::table('organization_user', function (Blueprint $table) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            });
            Schema::table('organization_user_admin', function (Blueprint $table) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'can_create_leagues')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('can_create_leagues', 'can_create_organizations');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'can_create_organizations')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('can_create_organizations', 'can_create_leagues');
            });
        }

        if (Schema::hasTable('organizations') && ! Schema::hasTable('leagues')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
            Schema::table('organization_user', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
            Schema::table('organization_user_admin', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });

            Schema::table('players', function (Blueprint $table) {
                $table->renameColumn('organization_id', 'league_id');
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->renameColumn('organization_id', 'league_id');
            });
            Schema::table('organization_user', function (Blueprint $table) {
                $table->renameColumn('organization_id', 'league_id');
            });
            Schema::table('organization_user_admin', function (Blueprint $table) {
                $table->renameColumn('organization_id', 'league_id');
            });

            Schema::rename('organizations', 'leagues');
            Schema::rename('organization_user', 'league_user');
            Schema::rename('organization_user_admin', 'league_user_admin');

            Schema::table('players', function (Blueprint $table) {
                $table->foreign('league_id')->references('id')->on('leagues')->nullOnDelete();
            });
            Schema::table('seasons', function (Blueprint $table) {
                $table->foreign('league_id')->references('id')->on('leagues')->nullOnDelete();
            });
            Schema::table('league_user', function (Blueprint $table) {
                $table->foreign('league_id')->references('id')->on('leagues')->cascadeOnDelete();
            });
            Schema::table('league_user_admin', function (Blueprint $table) {
                $table->foreign('league_id')->references('id')->on('leagues')->cascadeOnDelete();
            });
        }
    }
};
