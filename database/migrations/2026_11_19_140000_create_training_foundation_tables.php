<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('difficulty', 16)->nullable();
            $table->unsignedSmallInteger('declared_minutes')->nullable();
            $table->string('mechanic', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('dart_count')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });

        Schema::create('training_definition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('training_definitions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('item_kind', 16);
            $table->foreignId('exercise_definition_id')->nullable()->constrained('training_definitions')->restrictOnDelete();
            $table->unsignedSmallInteger('break_seconds')->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'position']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_session_id');
            $table->foreignId('definition_id')->constrained('training_definitions')->restrictOnDelete();
            $table->string('kind', 16);
            $table->timestamp('completed_at');
            $table->integer('score')->nullable();
            $table->json('stats');
            $table->timestamps();

            $table->unique(['user_id', 'client_session_id']);
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_definition_items');
        Schema::dropIfExists('training_definitions');
    }
};
