<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dla SQLite: upewnia się, że kolumna status w quick_game_lobbies
 * akceptuje wartość 'started' (brak CHECK ograniczającego do 'waiting').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('quick_game_lobbies', function (Blueprint $table) {
            $table->string('status', 20)->default('waiting')->change();
        });
    }

    public function down(): void
    {
        // Nie cofamy – zmiana jest kompatybilna wstecz.
    }
};
