<?php

use App\Support\Roster\SeasonRosterMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (new SeasonRosterMigrator)->move();

        Schema::dropIfExists('season_invitations');
        Schema::dropIfExists('season_user');
    }

    public function down(): void
    {
        // Skład sezonu został scalony ze składem organizacji. Cofnięcie zgubiłoby,
        // którzy użytkownicy i goście byli tylko na sezonie.
    }
};
