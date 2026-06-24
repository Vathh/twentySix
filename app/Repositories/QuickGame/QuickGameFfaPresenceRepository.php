<?php

namespace App\Repositories\QuickGame;

use App\Models\QuickGame\QuickGameFfaPresence;
use App\Models\QuickGame\QuickGameFfaSession;
use Illuminate\Support\Collection;

class QuickGameFfaPresenceRepository
{
    /**
     * @param  array<int, int>  $playerIds
     */
    public function initializeForSession(QuickGameFfaSession $session, array $playerIds): void
    {
        $now = now();

        foreach ($playerIds as $playerId) {
            QuickGameFfaPresence::query()->updateOrCreate(
                [
                    'ffa_session_id' => $session->id,
                    'player_id' => (int) $playerId,
                ],
                [
                    'status' => QuickGameFfaPresence::STATUS_CONNECTED,
                    'last_seen_at' => $now,
                    'left_at' => null,
                ],
            );
        }
    }

    /**
     * @return Collection<int, QuickGameFfaPresence>
     */
    public function getForSession(QuickGameFfaSession $session): Collection
    {
        return QuickGameFfaPresence::query()
            ->where('ffa_session_id', $session->id)
            ->with('player')
            ->get();
    }

    public function findForSessionPlayer(QuickGameFfaSession $session, int $playerId): ?QuickGameFfaPresence
    {
        return QuickGameFfaPresence::query()
            ->where('ffa_session_id', $session->id)
            ->where('player_id', $playerId)
            ->first();
    }

    public function save(QuickGameFfaPresence $presence): void
    {
        $presence->save();
    }

    /**
     * @param  array<int, int>  $playerIds
     */
    public function markStaleAsDisconnected(QuickGameFfaSession $session, array $playerIds, int $timeoutSeconds): void
    {
        $cutoff = now()->subSeconds($timeoutSeconds);

        QuickGameFfaPresence::query()
            ->where('ffa_session_id', $session->id)
            ->whereIn('player_id', $playerIds)
            ->where('status', QuickGameFfaPresence::STATUS_CONNECTED)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $cutoff);
            })
            ->update(['status' => QuickGameFfaPresence::STATUS_DISCONNECTED]);
    }

    /**
     * @return array<int, int>
     */
    public function getLeftPlayerIds(QuickGameFfaSession $session): array
    {
        return QuickGameFfaPresence::query()
            ->where('ffa_session_id', $session->id)
            ->where('status', QuickGameFfaPresence::STATUS_LEFT)
            ->pluck('player_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
