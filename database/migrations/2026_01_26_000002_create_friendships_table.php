<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('friend_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Unikalność - użytkownik nie może być znajomym samego siebie
            // i nie może mieć duplikatów relacji
            $table->unique(['user_id', 'friend_id']);
            
            // Zapobieganie duplikatom w drugą stronę (A->B i B->A)
            // Używamy check constraint przez unique index
            $table->index(['user_id', 'friend_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
