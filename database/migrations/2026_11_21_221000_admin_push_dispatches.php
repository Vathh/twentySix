<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_push_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('audience', 64);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->boolean('test_only')->default(false);
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_dispatches');
    }
};
