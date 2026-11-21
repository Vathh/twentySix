<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_definition_items', function (Blueprint $table) {
            $table->boolean('scored')->default(true)->after('dart_count');
            $table->string('variant', 32)->nullable()->after('scored');
        });
    }

    public function down(): void
    {
        Schema::table('training_definition_items', function (Blueprint $table) {
            $table->dropColumn(['scored', 'variant']);
        });
    }
};
