<?php

namespace App\Http\Controllers\Api;

use App\Services\QuickGame\QuickGameLobbyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameLobbyController
{
    public function __construct(
        private QuickGameLobbyService $lobbyService
    ) {
    }

    /**
     * POST /api/quick-game/lobby/create
     * Tworzy nowe lobby. Wymaga zalogowanego użytkownika (host).
     */
    public function create(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $lobby = $this->lobbyService->create($userId);
        return response()->json($this->lobbyToArray($lobby, $userId));
    }

    /**
     * GET /api/quick-game/lobby/{lobbyId}
     */
    public function get(Request $request, string $lobbyId): JsonResponse
    {
        $lobby = $this->lobbyService->get((int) $lobbyId);
        $currentUserId = $request->user()?->id;
        return response()->json($this->lobbyToArray($lobby, $currentUserId));
    }

    /**
     * GET /api/quick-game/lobby/code/{code}
     */
    public function getByCode(Request $request, string $code): JsonResponse
    {
        $lobby = $this->lobbyService->getByCode($code);
        if (!$lobby) {
            return response()->json(['message' => 'Lobby nie zostało znalezione'], 404);
        }
        $currentUserId = $request->user()?->id;
        return response()->json($this->lobbyToArray($lobby, $currentUserId));
    }

    /**
     * POST /api/quick-game/lobby/join
     * Dołączenie po kodzie (body: code, opcjonalnie tempPlayerName).
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
            'tempPlayerName' => 'nullable|string|max:50',
        ]);
        try {
            $userId = $request->user()?->id;
            $lobby = $this->lobbyService->joinByCode(
                $validated['code'],
                $userId,
                $validated['tempPlayerName'] ?? null
            );
            return response()->json($this->lobbyToArray($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/join
     * Dołączenie po ID (np. z zaproszenia).
     */
    public function joinById(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->joinById((int) $lobbyId, $userId);
            return response()->json($this->lobbyToArray($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/leave
     */
    public function leave(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $this->lobbyService->leave((int) $lobbyId, $userId);
            return response()->json(['success' => true]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/ready
     * Przełącza status gotowości gracza.
     */
    public function setReady(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->setReady((int) $lobbyId, $userId, true);
            return response()->json($this->lobbyToArray($lobby));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/start
     * Rozpoczyna mecz (tylko host).
     */
    /**
     * PATCH /api/quick-game/lobby/{lobbyId}
     * Aktualizuje ustawienia lobby (tylko host). Body: legsCount?, gameType?
     */
    public function updateSettings(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        $legsCount = $request->input('legsCount');
        $gameType = $request->input('gameType');
        $scoringMode = $request->input('scoringMode');
        try {
            $lobby = $this->lobbyService->updateSettings(
                (int) $lobbyId,
                $userId,
                $legsCount !== null ? (int) $legsCount : null,
                $gameType !== null && in_array($gameType, ['501', 'cricket'], true) ? $gameType : null
            );
            if ($scoringMode !== null && in_array($scoringMode, ['one_device', 'each_own'], true)) {
                $lobby = $this->lobbyService->updateScoringMode((int) $lobbyId, $userId, $scoringMode);
            }
            return response()->json($this->lobbyToArray($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function start(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        $legsCount = $request->input('legsCount');
        $gameType = $request->input('gameType');
        $scoringMode = $request->input('scoringMode');
        $playerOrderIds = $request->input('playerOrder');
        if (!is_array($playerOrderIds)) {
            $playerOrderIds = null;
        }
        try {
            $lobby = $this->lobbyService->startGame(
                (int) $lobbyId,
                $userId,
                $legsCount !== null ? (int) $legsCount : null,
                $gameType !== null && in_array($gameType, ['501', 'cricket'], true) ? $gameType : null,
                $scoringMode !== null && in_array($scoringMode, ['one_device', 'each_own'], true) ? $scoringMode : null,
                $playerOrderIds
            );
            $lobby->load(['host.player', 'players.player', 'session']);
            $orderedPlayers = $this->getOrderedLobbyPlayers($lobby);
            $players = $orderedPlayers->map(function ($p) {
                return [
                    'id' => $p->id,
                    'playerId' => $p->player_id,
                    'name' => $p->player?->name ?? $p->temp_player_name ?? 'Gracz',
                ];
            })->values()->all();
            $sessionId = $lobby->session?->id;
            $isHost = (int) $lobby->host_id === (int) $userId;
            $myPlayerIndex = null;
            foreach ($orderedPlayers as $i => $lp) {
                if ($lp->player_id && $lp->player && (int) $lp->player->user_id === (int) $userId) {
                    $myPlayerIndex = $i;
                    break;
                }
            }
            return response()->json([
                'id' => $lobby->id,
                'code' => $lobby->code,
                'hostId' => $lobby->host_id,
                'status' => $lobby->status,
                'legsCount' => (int) ($lobby->legs_count ?? 3),
                'gameType' => $lobby->game_type ?? '501',
                'players' => $players,
                'sessionId' => $sessionId,
                'scoringMode' => $lobby->scoring_mode ?? 'each_own',
                'isHost' => $isHost,
                'myPlayerIndex' => $myPlayerIndex,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/invite
     * Wysyła zaproszenie do lobby (body: playerId).
     */
    public function invite(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
        ]);
        $userId = $request->user()->id;
        try {
            $this->lobbyService->invite((int) $lobbyId, $userId, $validated['playerId']);
            return response()->json(['message' => 'Zaproszenie wysłane'], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/quick-game/lobby/invitations
     * Pobiera oczekujące zaproszenia do lobby dla zalogowanego użytkownika.
     */
    public function myInvitations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = $this->lobbyService->getPendingInvitationsForUser($userId);
        return response()->json(['invitations' => $invitations]);
    }

    /**
     * POST /api/quick-game/lobby/invitations/{invitationId}/reject
     * Odrzuca zaproszenie do lobby.
     */
    public function rejectInvitation(Request $request, int $invitationId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $this->lobbyService->rejectInvitation($invitationId, $userId);
            return response()->json(['message' => 'Zaproszenie odrzucone'], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/quick-game/lobby/{lobbyId}/add-guest
     * Dodaje gracza tymczasowego (gościa) do lobby – tylko nazwa, bez konta. Tylko host.
     */
    public function addGuest(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'tempPlayerName' => 'required|string|max:50',
        ]);
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->addGuest((int) $lobbyId, $userId, $validated['tempPlayerName']);
            return response()->json($this->lobbyToArray($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function lobbyToArray($lobby, ?int $currentUserId = null): array
    {
        $hostId = $lobby->host_id;
        $orderedPlayers = $this->getOrderedLobbyPlayers($lobby);
        $players = $orderedPlayers->map(function ($p) use ($hostId) {
            $isRegistered = $p->player_id !== null;
            $isHost = $p->player && (int) $p->player->user_id === (int) $hostId;
            return [
                'id' => $p->id,
                'playerId' => $p->player_id,
                'name' => $p->player?->name ?? null,
                'tempName' => $p->temp_player_name,
                'ready' => (bool) $p->is_ready,
                'isRegistered' => $isRegistered,
                'isHost' => $isHost,
            ];
        })->values()->all();

        $out = [
            'id' => $lobby->id,
            'code' => $lobby->code,
            'hostId' => $lobby->host_id,
            'status' => $lobby->status,
            'legsCount' => $lobby->legs_count ?? 3,
            'gameType' => $lobby->game_type ?? '501',
            'scoringMode' => $lobby->scoring_mode ?? 'each_own',
            'youAreHost' => $currentUserId !== null && (int) $lobby->host_id === (int) $currentUserId,
            'players' => $players,
        ];

        if ($lobby->status === 'started') {
            $lobby->load('session');
            if ($lobby->relationLoaded('session') && $lobby->session) {
                $out['sessionId'] = $lobby->session->id;
            }
            $out['myPlayerIndex'] = null;
            if ($currentUserId !== null) {
                foreach ($orderedPlayers as $i => $lp) {
                    if ($lp->player_id && $lp->player && (int) $lp->player->user_id === (int) $currentUserId) {
                        $out['myPlayerIndex'] = $i;
                        break;
                    }
                }
            }
        }

        return $out;
    }

    private function getOrderedLobbyPlayers($lobby)
    {
        $players = $lobby->players;
        if ($lobby->status !== 'started') {
            return $players->values();
        }

        $lobby->load('session');
        $sessionState = $lobby->session?->state;
        $orderIds = is_array($sessionState) ? ($sessionState['playerOrderLobbyPlayerIds'] ?? null) : null;
        if (!is_array($orderIds) || count($orderIds) === 0) {
            return $players->values();
        }

        $orderPos = array_flip(array_map('intval', $orderIds));
        return $players->sortBy(function ($p) use ($orderPos) {
            $id = (int) $p->id;
            return $orderPos[$id] ?? (10000 + $id);
        })->values();
    }
}









