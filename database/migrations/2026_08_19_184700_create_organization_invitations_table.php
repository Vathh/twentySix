<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled', 'removed'])
                ->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
