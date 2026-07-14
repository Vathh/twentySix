<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedSmallInteger('playoff_bracket_size')->nullable()->after('advance_per_group');
            $table->json('group_advances')->nullable()->after('playoff_bracket_size');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['playoff_bracket_size', 'group_advances']);
        });
    }
};
