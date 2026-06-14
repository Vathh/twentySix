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
}
