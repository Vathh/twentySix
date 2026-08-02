<?php

namespace App\Repositories\QuickGame;

use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameFfaVisit;
use Illuminate\Support\Collection;

class QuickGameFfaSessionRepository
{
    public function create(array $attributes): QuickGameFfaSession
    {
        return QuickGameFfaSession::create($attributes);
    }

    public function findForLobby(int $lobbyId): ?QuickGameFfaSession
    {
        return QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
    }

    public function findById(int $sessionId): ?QuickGameFfaSession
    {
        return QuickGameFfaSession::query()->find($sessionId);
    }

    public function findOrFailForLobby(int $lobbyId): QuickGameFfaSession
    {
        return QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
    }

    public function save(QuickGameFfaSession $session): void
    {
        $session->save();
    }

    public function incrementVersion(QuickGameFfaSession $session): void
    {
        $session->state_version = (int) $session->state_version + 1;
    }

    /**
     * @return Collection<int, QuickGameFfaSession>
     */
    public function findInProgressContainingPlayer(int $playerId): Collection
    {
        return QuickGameFfaSession::query()
            ->where('status', QuickGameFfaSession::STATUS_IN_PROGRESS)
            ->whereJsonContains('player_order', (int) $playerId)
            ->get();
    }

    /**
     * Sesje w toku zawierające gracza, których lobby jest wystartowane (np. do wznowienia aktywnego meczu).
     *
     * @return Collection<int, QuickGameFfaSession>
     */
    public function findInProgressForPlayerWithStartedLobby(int $playerId): Collection
    {
        return QuickGameFfaSession::query()
            ->where('status', QuickGameFfaSession::STATUS_IN_PROGRESS)
            ->whereJsonContains('player_order', (int) $playerId)
            ->whereHas('lobby', fn ($q) => $q->where('status', 'started'))
            ->with(['lobby.players.player'])
            ->get();
    }
}
