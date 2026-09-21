<?php

namespace App\Http\Controllers\Api;

use App\Domain\GameScoring\VisitDartPayload;
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
    ) {}

    public function state(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->ffaScoringService->getState((int) $lobbyId, $request->user()->id)
        );
    }

    public function updatePresence(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:connected,disconnected,left',
        ]);

        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->presenceService->updatePresence(
                (int) $lobbyId,
                $request->user()->id,
                $validated['status'],
            )
        );
    }

    public function activeMatch(Request $request): JsonResponse
    {
        $match = $this->presenceService->findActiveMatchForUser($request->user()->id);

        return response()->json([
            'match' => $match,
        ]);
    }

    public function abort(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);
        $this->lobbyService->abortStartedGame((int) $lobbyId, $request->user()->id);

        return response()->json(['success' => true, 'aborted' => true]);
    }

    public function recordVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'playerId' => 'required|integer|exists:players,id',
            'score' => 'required|integer|min:0|max:180',
            'remainingBefore' => 'required|integer|min:0|max:1001',
            'remainingAfter' => 'required|integer|min:0|max:1001',
            'dartsInVisit' => 'required|integer|min:1|max:3',
            'closedLeg' => 'boolean',
            'bust' => 'boolean',
            'clientVisitId' => 'required|uuid',
        ], VisitDartPayload::validationRules()));

        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->ffaScoringService->recordVisit(
                (int) $lobbyId,
                $request->user()->id,
                RecordFfaVisitDTO::fromArray($validated),
            )
        );
    }

    public function undoVisit(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->ffaScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
        );
    }

    public function closeLeg(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'winnerPlayerId' => 'required|integer|exists:players,id',
        ]);

        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->ffaScoringService->closeLegByBullOff(
                (int) $lobbyId,
                $request->user()->id,
                (int) $validated['winnerPlayerId'],
            )
        );
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
    }

    public function recordCricketVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'clientVisitId' => 'required|uuid',
            'darts' => 'required|array|min:1|max:3',
            'darts.*.kind' => 'required|string|in:hit,miss',
            'darts.*.segment' => 'nullable',
            'darts.*.multiplier' => 'integer|min:1|max:3',
            'darts.*.clientDartId' => 'nullable|uuid',
        ]);

        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        $darts = [];
        foreach ($validated['darts'] as $dart) {
            $segment = $dart['segment'] ?? null;
            if ($segment !== null && $segment !== 'bull') {
                $segment = (string) $segment;
            }
            $darts[] = [
                'kind' => $dart['kind'],
                'segment' => $segment,
                'multiplier' => (int) ($dart['multiplier'] ?? 1),
                'clientDartId' => $dart['clientDartId'] ?? $validated['clientVisitId'],
            ];
        }

        return response()->json(
            $this->cricketScoringService->recordVisit(
                (int) $lobbyId,
                $request->user()->id,
                (int) $validated['playerId'],
                $darts,
                $validated['clientVisitId'],
            )
        );
    }

    public function undoCricketDart(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->cricketScoringService->undoLastDart((int) $lobbyId, $request->user()->id)
        );
    }

    public function recordBob27Dart(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'hits' => 'required|integer|min:0|max:3',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
        ]);

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
    }

    public function undoBob27Dart(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->bob27ScoringService->undoLastDart((int) $lobbyId, $request->user()->id)
        );
    }

    public function recordAtcVisit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'hits' => 'required|integer|min:0|max:3',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
        ]);

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
    }

    public function undoAtcVisit(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->atcScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
        );
    }

    public function recordCatch40Visit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'playerId' => 'required|integer|exists:players,id',
            'score' => 'required|integer|min:0|max:180',
            'remainingBefore' => 'required|integer|min:0|max:100',
            'remainingAfter' => 'required|integer|min:0|max:100',
            'dartsInVisit' => 'required|integer|min:1|max:3',
            'checkout' => 'boolean',
            'bust' => 'boolean',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
        ], VisitDartPayload::validationRules()));

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
                VisitDartPayload::normalize($validated['darts'] ?? null),
            )
        );
    }

    public function undoCatch40Visit(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->catch40ScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
        );
    }

    public function recordCricket56Visit(Request $request, string $lobbyId): JsonResponse
    {
        $validated = $request->validate([
            'playerId' => 'required|integer|exists:players,id',
            'points' => 'nullable|integer|min:0|max:9',
            'marks' => 'nullable|array|size:3',
            'marks.*' => 'integer|min:0|max:3',
            'clientVisitId' => 'required_without:clientDartId|nullable|uuid',
            'clientDartId' => 'required_without:clientVisitId|nullable|uuid',
        ]);

        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->cricket56ScoringService->recordVisit(
                (int) $lobbyId,
                $request->user()->id,
                (int) $validated['playerId'],
                (int) ($validated['points'] ?? 0),
                (string) ($validated['clientVisitId'] ?? $validated['clientDartId']),
                isset($validated['marks']) && is_array($validated['marks'])
                    ? array_map('intval', $validated['marks'])
                    : null,
            )
        );
    }

    public function undoCricket56Visit(Request $request, string $lobbyId): JsonResponse
    {
        $this->assertLobbyParticipant((int) $lobbyId, $request->user()->id);

        return response()->json(
            $this->cricket56ScoringService->undoLastVisit((int) $lobbyId, $request->user()->id)
        );
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
