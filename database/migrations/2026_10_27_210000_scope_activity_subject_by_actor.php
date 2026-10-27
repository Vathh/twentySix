<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['type', 'subject_type', 'subject_id']);
            $table->unique(
                ['type', 'subject_type', 'subject_id', 'actor_player_id'],
                'activities_subject_actor_unique',
            );
        });

        Artisan::call('activities:backfill-achievements');
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique('activities_subject_actor_unique');
            $table->unique(['type', 'subject_type', 'subject_id']);
        });
    }
};
