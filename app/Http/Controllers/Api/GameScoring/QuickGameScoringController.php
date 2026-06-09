<?php

namespace App\Http\Controllers\Api\GameScoring;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Http\Controllers\Controller;
use App\Services\GameScoring\GameScoringService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameScoringController extends Controller
{
    public function __construct(
        private GameScoringService $gameScoringService,
    ) {
    }

    public function state(int $quickGameId): JsonResponse
    {
        try {
            [$context, $game] = $this->gameScoringService->resolveQuickGame($quickGameId);

            return response()->json($this->gameScoringService->getState($context, $game));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function startLeg(Request $request, int $quickGameId): JsonResponse
    {
        $validated = $request->validate([
            'player1DoubleTracked' => 'required|boolean',
            'player2DoubleTracked' => 'required|boolean',
        ]);

        try {
            [$context, $game] = $this->gameScoringService->resolveQuickGame($quickGameId);

            return response()->json(
                $this->gameScoringService->startLeg(
                    $context,
                    $game,
                    $validated['player1DoubleTracked'],
                    $validated['player2DoubleTracked'],
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordVisit(Request $request, int $quickGameId, int $legId): JsonResponse
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
            [$context, $game] = $this->gameScoringService->resolveQuickGame($quickGameId);

            return response()->json(
                $this->gameScoringService->recordVisit(
                    $context,
                    $game,
                    $legId,
                    RecordVisitDTO::fromArray($validated),
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function undoVisit(int $quickGameId, int $legId): JsonResponse
    {
        try {
            [$context, $game] = $this->gameScoringService->resolveQuickGame($quickGameId);

            return response()->json(
                $this->gameScoringService->undoLastVisit($context, $game, $legId)
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function closeLeg(Request $request, int $quickGameId, int $legId): JsonResponse
    {
        $validated = $request->validate([
            'winnerId' => 'required|integer|exists:players,id',
            'players' => 'required|array|min:1|max:2',
            'players.*.playerId' => 'required|integer|exists:players,id',
            'players.*.doubleTracked' => 'required|boolean',
            'players.*.doubleAttempts' => 'nullable|integer|min:0',
            'players.*.doubleSuccesses' => 'nullable|integer|min:0',
            'players.*.legAverage' => 'nullable|numeric',
            'players.*.firstNineAverage' => 'nullable|numeric',
            'players.*.highestVisit' => 'nullable|integer|min:0|max:180',
            'players.*.highestFinish' => 'nullable|integer|min:0|max:180',
            'players.*.dartsThrown' => 'nullable|integer|min:0',
            'players.*.checkoutDart' => 'nullable|integer|min:1|max:3',
        ]);

        try {
            [$context, $game] = $this->gameScoringService->resolveQuickGame($quickGameId);
            $playerStats = array_map(
                fn (array $row) => CloseLegPlayerStatsDTO::fromArray($row),
                $validated['players'],
            );

            return response()->json(
                $this->gameScoringService->closeLeg(
                    $context,
                    $game,
                    $legId,
                    (int) $validated['winnerId'],
                    $playerStats,
                )
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
