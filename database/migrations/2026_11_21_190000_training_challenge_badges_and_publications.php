<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_definitions', function (Blueprint $table) {
            $table->json('badge_thresholds')->nullable()->after('mechanic');
        });

        Schema::create('training_challenge_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('training_definitions')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('training_sessions')->restrictOnDelete();
            $table->integer('score');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['user_id', 'definition_id'], 'tcp_user_definition_unique');
            $table->index(['definition_id', 'score'], 'tcp_definition_score_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_challenge_publications');
        Schema::table('training_definitions', function (Blueprint $table) {
            $table->dropColumn('badge_thresholds');
        });
    }
};
