<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence')->nullable()->after('group_number');
            $table->unsignedBigInteger('referee_player_id')->nullable()->after('sequence');
            $table->foreign('referee_player_id')
                ->references('id')
                ->on('players')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['referee_player_id']);
            $table->dropColumn(['referee_player_id', 'sequence']);
        });
    }
};
