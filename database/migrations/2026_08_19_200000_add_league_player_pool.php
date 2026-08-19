<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Niedokończona próba: InnoDB zostawia tabelę, gdy FK nie wejdzie.
        // Nazwy `league_user_*_foreign` kolidują z organization_user
        // (dawna tabela league_user, MySQL nie zmienia nazw constraintów przy RENAME).
        Schema::dropIfExists('league_user');

        Schema::create('league_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('league_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['league_id', 'user_id'], 'league_pool_user_unique');
            $table->foreign('league_id', 'league_pool_user_league_id_foreign')
                ->references('id')
                ->on('leagues')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'league_pool_user_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        if (! Schema::hasColumn('players', 'league_id')) {
            Schema::table('players', function (Blueprint $table) {
                $table->unsignedBigInteger('league_id')->nullable()->after('season_id');
                $table->foreign('league_id', 'players_league_pool_id_foreign')
                    ->references('id')
                    ->on('leagues')
                    ->nullOnDelete();
            });
        }

        $now = now();
        $leagues = DB::table('leagues')->get(['id', 'organization_id']);

        foreach ($leagues as $league) {
            $orgUserIds = DB::table('organization_user')
                ->where('organization_id', $league->organization_id)
                ->pluck('user_id');

            $assignedUserIds = DB::table('league_division_members')
                ->join('players', 'players.id', '=', 'league_division_members.player_id')
                ->where('league_division_members.league_id', $league->id)
                ->whereNotNull('players.user_id')
                ->pluck('players.user_id');

            foreach ($orgUserIds->merge($assignedUserIds)->unique() as $userId) {
                DB::table('league_user')->insertOrIgnore([
                    'league_id' => $league->id,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $assignedGuestIds = DB::table('league_division_members')
                ->join('players', 'players.id', '=', 'league_division_members.player_id')
                ->where('league_division_members.league_id', $league->id)
                ->whereNull('players.user_id')
                ->pluck('players.id');

            if ($assignedGuestIds->isNotEmpty()) {
                DB::table('players')
                    ->whereIn('id', $assignedGuestIds)
                    ->update(['league_id' => $league->id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('players', 'league_id')) {
            Schema::table('players', function (Blueprint $table) {
                $table->dropForeign('players_league_pool_id_foreign');
                $table->dropColumn('league_id');
            });
        }
        Schema::dropIfExists('league_user');
    }
};
