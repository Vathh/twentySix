<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('training_definitions')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['user_id', 'definition_id']);
            $table->unique(['user_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_favorites');
    }
};
