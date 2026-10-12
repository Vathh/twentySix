<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webcam_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('lobby');
            $table->unsignedSmallInteger('leg_number')->default(1);
            $table->foreignId('current_turn_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('host_legs_won')->default(0);
            $table->unsignedTinyInteger('guest_legs_won')->default(0);
            $table->timestamp('host_ready_at')->nullable();
            $table->timestamp('guest_ready_at')->nullable();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['host_user_id', 'status']);
            $table->index(['guest_user_id', 'status']);
        });

        Schema::create('webcam_match_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webcam_match_id')->constrained('webcam_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('leg_number');
            $table->unsignedSmallInteger('visit_number');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('remaining_before');
            $table->unsignedSmallInteger('remaining_after');
            $table->unsignedTinyInteger('darts_in_visit');
            $table->boolean('bust')->default(false);
            $table->boolean('closed_leg')->default(false);
            $table->timestamps();

            $table->index(['webcam_match_id', 'leg_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webcam_match_visits');
        Schema::dropIfExists('webcam_matches');
    }
};
