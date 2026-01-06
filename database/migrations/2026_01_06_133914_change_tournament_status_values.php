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
            $table->enum('status', ['created', 'group', 'playoff', 'finished'])
                ->default('created')
                ->change();
        });

        DB::table('tournaments')
            ->where('status', 'started')
            ->update(['status' => 'group']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tournaments')
            ->where('status', 'group')
            ->update(['status' => 'started']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->enum('status', ['created', 'started', 'finished'])
                ->default('created')
                ->change();
        });
    }
};
