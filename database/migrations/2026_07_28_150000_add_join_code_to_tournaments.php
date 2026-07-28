<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('join_code', 16)->nullable()->unique()->after('tablets_count');
            $table->timestamp('join_code_generated_at')->nullable()->after('join_code');
            $table->boolean('join_code_enabled')->default(true)->after('join_code_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['join_code', 'join_code_generated_at', 'join_code_enabled']);
        });
    }
};
