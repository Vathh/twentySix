<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'removed'])
                ->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['season_id', 'status']);
        });

        Schema::create('league_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'removed'])
                ->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['league_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_invitations');
        Schema::dropIfExists('season_invitations');
    }
};
