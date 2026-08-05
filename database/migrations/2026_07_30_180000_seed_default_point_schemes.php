<?php

use Database\Seeders\PointSchemeSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Schematy punktacji to dane referencyjne potrzebne do startu turniejów ligowych.
 * Trzymamy je w migracji (nie tylko w --seed), żeby migrate:fresh na stagingu
 * nie wymagał osobnego ręcznego seeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PointSchemeSeeder)->run();
    }

    public function down(): void
    {
        // Schematy mogą być powiązane z turniejami — nie kasujemy przy rollbacku.
    }
};
