<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_feed_visibility')->default('public')->after('email');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('actor_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['type', 'subject_type', 'subject_id']);
            $table->index(['actor_player_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['occurred_at', 'id']);
        });

        Schema::create('feed_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
        });

        Schema::create('feed_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_key');
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_key']);
        });

        \Illuminate\Support\Facades\Artisan::call('activities:backfill');
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_mutes');
        Schema::dropIfExists('feed_channel_settings');
        Schema::dropIfExists('activities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_feed_visibility');
        });
    }
};
