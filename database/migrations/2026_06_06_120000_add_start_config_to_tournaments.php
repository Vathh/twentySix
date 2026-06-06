<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedSmallInteger('groups_count')->nullable()->after('point_scheme_id');
            $table->unsignedSmallInteger('advance_per_group')->nullable()->after('groups_count');
            $table->unsignedSmallInteger('tablets_count')->nullable()->after('advance_per_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['groups_count', 'advance_per_group', 'tablets_count']);
        });
    }
};
