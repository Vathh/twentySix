<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'organizations',
        'seasons',
        'tournaments',
        'leagues',
        'league_seasons',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->softDeletes();
                $blueprint->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->string('cascade_from_type', 32)->nullable();
                $blueprint->unsignedBigInteger('cascade_from_id')->nullable();
                $blueprint->index(
                    ['cascade_from_type', 'cascade_from_id'],
                    $table.'_cascade_from_index',
                );
            });
        }

        Schema::table('leagues', function (Blueprint $blueprint) {
            $blueprint->index('organization_id', 'leagues_organization_id_index');
        });

        Schema::table('leagues', function (Blueprint $blueprint) {
            $blueprint->dropUnique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $blueprint) {
            $blueprint->unique(['organization_id', 'name']);
        });

        Schema::table('leagues', function (Blueprint $blueprint) {
            $blueprint->dropIndex('leagues_organization_id_index');
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_cascade_from_index');
                $blueprint->dropConstrainedForeignId('deleted_by_user_id');
                $blueprint->dropColumn(['cascade_from_type', 'cascade_from_id', 'deleted_at']);
            });
        }
    }
};
