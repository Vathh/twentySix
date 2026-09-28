<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->foreignId('champion_player_id')
                ->nullable()
                ->after('finished_at')
                ->constrained('players')
                ->nullOnDelete();
        });

        // Świeża migracja nie ma jeszcze zakończonych sezonów. Nie wołamy tu modelu:
        // SoftDeletes dokłada deleted_at, a ta kolumna powstaje dopiero w późniejszej migracji.
    }

    public function down(): void
    {
        Schema::table('league_seasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('champion_player_id');
        });
    }
};
