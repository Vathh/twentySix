<?php

namespace App\Support\Roster;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Przenosi skład sezonu (użytkownicy, oczekujące zaproszenia, goście) na organizację.
 * Nie scala wierszy graczy — `players.id` zostaje, żeby wyniki i turniejowe przypisania zostały.
 */
final class SeasonRosterMigrator
{
    public function move(): void
    {
        $this->moveRelatedUsers();
        $this->movePendingInvitations();
        $this->moveGuests();
    }

    private function moveRelatedUsers(): void
    {
        if (! Schema::hasTable('season_user')) {
            return;
        }

        $rows = DB::table('season_user')
            ->join('seasons', 'seasons.id', '=', 'season_user.season_id')
            ->select('season_user.user_id', 'seasons.organization_id')
            ->whereNotNull('seasons.organization_id')
            ->get();

        foreach ($rows as $row) {
            $this->attachOrganizationUser((int) $row->organization_id, (int) $row->user_id);
        }
    }

    private function movePendingInvitations(): void
    {
        if (! Schema::hasTable('season_invitations')) {
            return;
        }

        $pending = DB::table('season_invitations')
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $invitation) {
            $organizationId = DB::table('seasons')
                ->where('id', $invitation->season_id)
                ->value('organization_id');
            if ($organizationId === null) {
                continue;
            }

            $organizationId = (int) $organizationId;
            $userId = (int) $invitation->user_id;
            if ($this->organizationHasUser($organizationId, $userId)) {
                continue;
            }

            $existing = DB::table('organization_invitations')
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->first();
            if ($existing !== null && in_array($existing->status, ['pending', 'accepted'], true)) {
                continue;
            }
            if ($existing !== null) {
                continue;
            }

            DB::table('organization_invitations')->insert([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'invited_by' => $invitation->invited_by,
                'status' => 'pending',
                'responded_at' => null,
                'created_at' => $invitation->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function moveGuests(): void
    {
        $guests = DB::table('players')
            ->whereNull('user_id')
            ->whereNotNull('season_id')
            ->orderBy('id')
            ->get();

        foreach ($guests as $guest) {
            $organizationId = DB::table('seasons')
                ->where('id', $guest->season_id)
                ->value('organization_id');

            if ($organizationId === null) {
                DB::table('players')->where('id', $guest->id)->update([
                    'season_id' => null,
                    'updated_at' => now(),
                ]);

                continue;
            }

            $organizationId = (int) $organizationId;
            $currentOrganizationId = $guest->organization_id !== null ? (int) $guest->organization_id : null;

            if ($currentOrganizationId !== null && $currentOrganizationId !== $organizationId) {
                DB::table('players')->where('id', $guest->id)->update([
                    'season_id' => null,
                    'updated_at' => now(),
                ]);

                continue;
            }

            $name = $currentOrganizationId === null
                ? $this->availableName((string) $guest->name, $organizationId, (int) $guest->id)
                : (string) $guest->name;

            DB::table('players')->where('id', $guest->id)->update([
                'organization_id' => $organizationId,
                'season_id' => null,
                'name' => $name,
                'updated_at' => now(),
            ]);
        }
    }

    private function attachOrganizationUser(int $organizationId, int $userId): void
    {
        $exists = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('organization_user')->insert([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function organizationHasUser(int $organizationId, int $userId): bool
    {
        return DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function availableName(string $name, int $organizationId, int $playerId): string
    {
        $candidate = $name;
        $suffix = 2;

        while ($this->nameTaken($candidate, $organizationId, $playerId)) {
            $tail = ' ('.$suffix.')';
            $candidate = mb_substr($name, 0, 255 - mb_strlen($tail)).$tail;
            $suffix++;
        }

        return $candidate;
    }

    private function nameTaken(string $name, int $organizationId, int $playerId): bool
    {
        $guestTaken = DB::table('players')
            ->where('organization_id', $organizationId)
            ->where('id', '!=', $playerId)
            ->where('name', $name)
            ->exists();

        if ($guestTaken) {
            return true;
        }

        return DB::table('organization_user')
            ->join('players', 'players.user_id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organizationId)
            ->where('players.id', '!=', $playerId)
            ->where('players.name', $name)
            ->exists();
    }
}
