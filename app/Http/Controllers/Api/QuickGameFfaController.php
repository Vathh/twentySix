<?php

namespace App\Http\Controllers\Api;

use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Services\QuickGame\QuickGameFfaAtcScoringService;
use App\Services\QuickGame\QuickGameFfaBob27ScoringService;
use App\Services\QuickGame\QuickGameFfaCatch40ScoringService;
use App\Services\QuickGame\QuickGameFfaCricket56ScoringService;
use App\Services\QuickGame\QuickGameFfaCricketScoringService;
use App\Services\QuickGame\QuickGameFfaPresenceService;
use App\Services\QuickGame\QuickGameFfaScoringService;
use App\Services\QuickGame\QuickGameLobbyService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameFfaController
{
    public function __construct(
        private QuickGameFfaScoringService $ffaScoringService,
        private QuickGameFfaCricketScoringService $cricketScoringService,
        private QuickGameFfaBob27ScoringService $bob27ScoringService,
        private QuickGameFfaAtcScoringService $atcScoringService,
        private QuickGameFfaCatch40ScoringService $catch40ScoringService,
        private QuickGameFfaCricket56ScoringService $cricket56ScoringService,
        private QuickGameFfaPresenceService $presenceService,
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

    public function updatePresence(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:connected,disconnected,left',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->presenceService->updatePresence(
                    (int) $lobbyId,
                    $request->user()->id,
                    $validated['status'],
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function activeMatch(Request $request): JsonResponse
    {
        $match = $this->presenceService->findActiveMatchForUser($request->user()->id);

        return response()->json([
            'match' => $match,
        ]);
    }

    public function recordVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'score' => 'required|integer|min:0|max:180',
            'remainingBefore' => 'required|integer|min:0|max:1001',
            'remainingAfter' => 'required|integer|min:0|max:1001',
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

    public function recordCricketDart(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'kind' => 'required|string|in:hit,miss',
            'segment' => 'nullable',
            'multiplier' => 'integer|min:1|max:3',
            'clientDartId' => 'required|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            $segment = $validated['segment'] ?? null;
            if ($segment !== null && $segment !== 'bull') {
                $segment = (string) $segment;
            }

            return response()->json(
                $this->cricketScoringService->recordDart(
                    (int) $lobbyId,
                    $request->user()->id,
                    (int) $validated['playerId'],
                    $validated['kind'],
                    $segment,
                    (int) ($validated['multiplier'] ?? 1),
                    $validated['clientDartId'],
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoCricketDart(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->cricketScoringService->undoLastDart((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordBob27Dart(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'hits' => 'required|integer|min:0|max:3',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->bob27ScoringService->recordVisit(
                    (int) $lobbyId,
                    $request->user()->id,
                    (int) $validated['playerId'],
                    (int) $validated['hits'],
                    (string) ($validated['clientVisitId'] ?? $validated['clientDartId']),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoBob27Dart(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->bob27ScoringService->undoLastDart((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordAtcVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'hits' => 'required|integer|min:0|max:3',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->atcScoringService->recordVisit(
                    (int) $lobbyId,
                    $request->user()->id,
                    (int) $validated['playerId'],
                    (int) $validated['hits'],
                    (string) ($validated['clientVisitId'] ?? $validated['clientDartId']),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoAtcVisit(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->atcScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordCatch40Visit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'score' => 'required|integer|min:0|max:180',
            'remainingBefore' => 'required|integer|min:0|max:100',
            'remainingAfter' => 'required|integer|min:0|max:100',
            'dartsInVisit' => 'required|integer|min:1|max:3',
            'checkout' => 'boolean',
            'bust' => 'boolean',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->catch40ScoringService->recordVisit(
                    (int) $lobbyId,
                    $request->user()->id,
                    (int) $validated['playerId'],
                    (int) $validated['score'],
                    (int) $validated['remainingBefore'],
                    (int) $validated['remainingAfter'],
                    (int) $validated['dartsInVisit'],
                    (bool) ($validated['bust'] ?? false),
                    (bool) ($validated['checkout'] ?? false),
                    (string) ($validated['clientVisitId'] ?? $validated['clientDartId']),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoCatch40Visit(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->catch40ScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordCricket56Visit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'points' => 'required|integer|min:0|max:9',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
        ]);

        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->cricket56ScoringService->recordVisit(
                    (int) $lobbyId,
                    $request->user()->id,
                    (int) $validated['playerId'],
                    (int) $validated['points'],
                    (string) ($validated['clientVisitId'] ?? $validated['clientDartId']),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoCricket56Visit(Request $request, string $lobbyId): JsonResponse
    {
        try {
            $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

            return response()->json(
                $this->cricket56ScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function assertLobbyParticipant(int $lobbyId, int $userId): void
    {
        $lobby = $this->lobbyService->get($lobbyId);
        if ($this->presenceService->isFfaParticipant($lobby, $userId)) {
            return;
        }

        throw new DomainException('Nie jesteś uczestnikiem tego lobby.');
    }
}
