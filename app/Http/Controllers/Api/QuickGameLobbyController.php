<?php

namespace App\Http\Controllers\Api;

use App\Services\QuickGameLobbyService;
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
    public function start(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->startGame((int) $lobbyId, $userId);
            $lobby->load(['host.player', 'players.player']);
            $players = $lobby->players->map(function ($p) {
                return [
                    'id' => $p->id,
                    'playerId' => $p->player_id,
                    'name' => $p->player?->name ?? $p->temp_player_name ?? 'Gracz',
                ];
            })->values()->all();
            return response()->json([
                'id' => $lobby->id,
                'code' => $lobby->code,
                'hostId' => $lobby->host_id,
                'status' => $lobby->status,
                'players' => $players,
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
        // TODO: implementacja zaproszeń do lobby (np. tabela lobby_invitations)
        return response()->json(['message' => 'Zaproszenie wysłane'], 200);
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
        $players = $lobby->players->map(function ($p) use ($hostId) {
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

        return [
            'id' => $lobby->id,
            'code' => $lobby->code,
            'hostId' => $lobby->host_id,
            'status' => $lobby->status,
            'legsCount' => $lobby->legs_count ?? 3,
            'youAreHost' => $currentUserId !== null && (int) $lobby->host_id === (int) $currentUserId,
            'players' => $players,
        ];
    }
}
