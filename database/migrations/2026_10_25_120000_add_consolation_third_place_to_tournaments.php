<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('has_consolation_third_place')->default(false)->after('consolation_bracket_size');
        });

        DB::table('tournaments')
            ->where('has_consolation_bracket', true)
            ->where('consolation_bracket_size', '>=', 4)
            ->update(['has_consolation_third_place' => true]);
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('has_consolation_third_place');
        });
    }
};
