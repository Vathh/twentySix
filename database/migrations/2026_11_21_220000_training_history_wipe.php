<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_history_wipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('wiped_at');
            $table->dateTime('purge_after');
            $table->dateTime('restored_at')->nullable();
            $table->timestamps();

            $table->index(['purge_after', 'restored_at']);
            $table->index(['user_id', 'restored_at']);
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreignId('wipe_id')
                ->nullable()
                ->after('stats')
                ->constrained('training_history_wipes')
                ->nullOnDelete();
            $table->index('wipe_id');
        });

        Schema::table('training_challenge_publications', function (Blueprint $table) {
            $table->foreignId('wipe_id')
                ->nullable()
                ->after('published_at')
                ->constrained('training_history_wipes')
                ->nullOnDelete();
            $table->index('wipe_id');
        });

        Schema::table('training_event_attempts', function (Blueprint $table) {
            $table->foreignId('wipe_id')
                ->nullable()
                ->after('stats')
                ->constrained('training_history_wipes')
                ->nullOnDelete();
            $table->index('wipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_event_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wipe_id');
        });
        Schema::table('training_challenge_publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wipe_id');
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wipe_id');
        });
        Schema::dropIfExists('training_history_wipes');
    }
};
