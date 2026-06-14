<?php

namespace App\Http\Controllers\Api;

use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Services\QuickGame\QuickGameFfaScoringService;
use App\Services\QuickGame\QuickGameLobbyService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameFfaController
{
    public function __construct(
        private QuickGameFfaScoringService $ffaScoringService,
        private QuickGameLobbyService $lobbyService,
    ) {
    }

    public function state(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->ffaScoringService->getState((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'score' => 'required|integer|min:0|max:180',
            'remainingBefore' => 'required|integer|min:0|max:501',
            'remainingAfter' => 'required|integer|min:0|max:501',
            'dartsInVisit' => 'required|integer|min:1|max:3',
            'closedLeg' => 'boolean',
            'bust' => 'boolean',
            'clientVisitId' => 'required|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->ffaScoringService->recordVisit(
                    (int) $lobbyId,
                    $request->user()->id,
                    RecordFfaVisitDTO::fromArray($validated),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoVisit(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->ffaScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function assertLobbyParticipant(int $lobbyId, int $userId): void
    {
        $lobby = $this->lobbyService->get($lobbyId);
        if ((int) $lobby->host_id === $userId) {
            return;
        }

        $player = $lobby->players->first(fn ($lp) => $lp->player && (int) $lp->player->user_id === $userId);
        if ($player === null) {
            throw new DomainException('Nie jesteś uczestnikiem tego lobby.');
        }
    }
}
