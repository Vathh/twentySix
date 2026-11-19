<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_definition_items', function (Blueprint $table) {
            $table->dropForeign(['exercise_definition_id']);
            $table->dropColumn('exercise_definition_id');
            $table->string('exercise_key', 64)->nullable()->after('item_kind');
            $table->unsignedInteger('duration_seconds')->nullable()->after('exercise_key');
            $table->unsignedSmallInteger('dart_count')->nullable()->after('duration_seconds');
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['definition_id']);
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('definition_id')->nullable()->change();
            $table->string('exercise_key', 64)->nullable()->after('definition_id');
            $table->foreign('definition_id')->references('id')->on('training_definitions')->restrictOnDelete();
        });

        Schema::table('training_favorites', function (Blueprint $table) {
            $table->dropForeign(['definition_id']);
        });
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->unsignedBigInteger('definition_id')->nullable()->change();
            $table->string('exercise_key', 64)->nullable()->after('definition_id');
            $table->foreign('definition_id')->references('id')->on('training_definitions')->cascadeOnDelete();
            $table->unique(['user_id', 'exercise_key']);
        });
    }

    public function down(): void
    {
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'exercise_key']);
            $table->dropForeign(['definition_id']);
            $table->dropColumn('exercise_key');
        });
        Schema::table('training_favorites', function (Blueprint $table) {
            $table->unsignedBigInteger('definition_id')->nullable(false)->change();
            $table->foreign('definition_id')->references('id')->on('training_definitions')->cascadeOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropForeign(['definition_id']);
            $table->dropColumn('exercise_key');
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('definition_id')->nullable(false)->change();
            $table->foreign('definition_id')->references('id')->on('training_definitions')->restrictOnDelete();
        });

        Schema::table('training_definition_items', function (Blueprint $table) {
            $table->dropColumn(['exercise_key', 'duration_seconds', 'dart_count']);
            $table->foreignId('exercise_definition_id')->nullable()->constrained('training_definitions')->restrictOnDelete();
        });
    }
};
