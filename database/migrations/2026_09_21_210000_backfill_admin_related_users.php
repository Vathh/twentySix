<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $admins = DB::table('organization_user_admin')
            ->select('organization_id', 'user_id')
            ->distinct()
            ->get();

        foreach ($admins as $admin) {
            $this->insertMissing('organization_user', 'organization_id', (int) $admin->organization_id, (int) $admin->user_id);
        }

        $seasonAdmins = DB::table('season_user_admin')
            ->select('season_id', 'user_id')
            ->distinct()
            ->get();

        foreach ($seasonAdmins as $admin) {
            $this->insertMissing('season_user', 'season_id', (int) $admin->season_id, (int) $admin->user_id);
        }

        $leagues = DB::table('leagues')->select('id', 'organization_id')->get();
        foreach ($leagues as $league) {
            $leagueAdminIds = DB::table('organization_user_admin')
                ->where('organization_id', $league->organization_id)
                ->distinct()
                ->pluck('user_id');

            foreach ($leagueAdminIds as $userId) {
                $this->insertMissing('league_user', 'league_id', (int) $league->id, (int) $userId);
            }
        }
    }

    public function down(): void
    {
        // Wpisy składu mogły powstać też z akceptacji zaproszenia — nie da się ich bezpiecznie cofnąć.
    }

    private function insertMissing(string $table, string $ownerColumn, int $ownerId, int $userId): void
    {
        $exists = DB::table($table)
            ->where($ownerColumn, $ownerId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table($table)->insert([
            $ownerColumn => $ownerId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
