<?php

namespace App\Http\Controllers\Api;

use App\Services\QuickGame\QuickGameLobbyService;
use App\Support\QuickGameLobbyPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameLobbyController
{
    public function __construct(
        private QuickGameLobbyService $lobbyService
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $lobby = $this->lobbyService->create($userId);

        return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $userId));
    }

    public function get(Request $request, string $lobbyId): JsonResponse
    {
        $lobby = $this->lobbyService->get((int) $lobbyId);
        $currentUserId = $request->user()?->id;

        return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $currentUserId));
    }

    public function joinById(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->joinById((int) $lobbyId, $userId);

            return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

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

    public function setReady(Request $request, string $lobbyId): JsonResponse
    {
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->setReady((int) $lobbyId, $userId, true);

            return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

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

            return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $userId));
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
        if (! is_array($playerOrderIds)) {
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
            $payload = QuickGameLobbyPayload::fromLobby($lobby, $userId);
            $payload['isHost'] = (int) $lobby->host_id === (int) $userId;

            return response()->json($payload);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

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

    public function myInvitations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = $this->lobbyService->getPendingInvitationsForUser($userId);

        return response()->json(['invitations' => $invitations]);
    }

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

    public function addGuest(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'tempPlayerName' => 'required|string|max:50',
        ]);
        $userId = $request->user()->id;
        try {
            $lobby = $this->lobbyService->addGuest((int) $lobbyId, $userId, $validated['tempPlayerName']);

            return response()->json(QuickGameLobbyPayload::fromLobby($lobby, $userId));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
