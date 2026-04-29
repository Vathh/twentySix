<?php

namespace App\Repositories\QuickGame;

use App\Models\QuickGameSession;

class QuickGameSessionRepository
{
    public function create(int $lobbyId, int $hostUserId, string $scoringMode, array $state): QuickGameSession
    {
        return QuickGameSession::create([
            'lobby_id' => $lobbyId,
            'host_user_id' => $hostUserId,
            'scoring_mode' => $scoringMode,
            'state' => $state,
        ]);
    }

    public function find(int $sessionId): QuickGameSession
    {
        return QuickGameSession::with('lobby.players.player')->findOrFail($sessionId);
    }

    public function updateState(int $sessionId, array $state): QuickGameSession
    {
        $session = QuickGameSession::findOrFail($sessionId);
        $session->update(['state' => $state]);
        return $session->fresh();
    }
}











