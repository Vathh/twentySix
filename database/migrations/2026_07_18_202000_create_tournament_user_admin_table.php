<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_user_admin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['tournament_id', 'user_id']);
            $table->timestamps();
        });

        $now = now();
        $tournaments = DB::table('tournaments')->whereNotNull('season_id')->get(['id', 'season_id']);
        foreach ($tournaments as $tournament) {
            $adminIds = DB::table('season_user_admin')
                ->where('season_id', $tournament->season_id)
                ->pluck('user_id');
            foreach ($adminIds as $userId) {
                DB::table('tournament_user_admin')->insertOrIgnore([
                    'tournament_id' => $tournament->id,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_user_admin');
    }
};
