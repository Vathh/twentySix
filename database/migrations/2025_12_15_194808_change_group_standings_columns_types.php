<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Historycznie zmieniało typy kolumn legs_* na signed.
 * Schema finalna jest już w create_group_standings — no-op przy migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
