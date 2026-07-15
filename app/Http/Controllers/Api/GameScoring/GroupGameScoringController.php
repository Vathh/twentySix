<?php

namespace App\Http\Controllers\Api\GameScoring;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Http\Controllers\Controller;
use App\Services\GameScoring\GameScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupGameScoringController extends Controller
{
    public function __construct(
        private GameScoringService $gameScoringService,
    ) {
    }

    public function state(int $gameId): JsonResponse
    {
        [$context, $game] = $this->gameScoringService->resolveGroupGame($gameId);

        return response()->json($this->gameScoringService->getState($context, $game));
    }

    public function startLeg(Request $request, int $gameId): JsonResponse
    {
        $validated = $request->validate([
            'player1DoubleTracked' => 'required|boolean',
            'player2DoubleTracked' => 'required|boolean',
        ]);

        [$context, $game] = $this->gameScoringService->resolveGroupGame($gameId);

        $state = $this->gameScoringService->startLeg(
            $context,
            $game,
            $validated['player1DoubleTracked'],
            $validated['player2DoubleTracked'],
        );

        return response()->json($state);
    }

    public function recordVisit(Request $request, int $gameId, int $legId): JsonResponse
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

        [$context, $game] = $this->gameScoringService->resolveGroupGame($gameId);
        $dto = RecordVisitDTO::fromArray($validated);

        return response()->json(
            $this->gameScoringService->recordVisit($context, $game, $legId, $dto)
        );
    }

    public function undoVisit(int $gameId, int $legId): JsonResponse
    {
        [$context, $game] = $this->gameScoringService->resolveGroupGame($gameId);

        return response()->json(
            $this->gameScoringService->undoLastVisit($context, $game, $legId)
        );
    }

    public function closeLeg(Request $request, int $gameId, int $legId): JsonResponse
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

        [$context, $game] = $this->gameScoringService->resolveGroupGame($gameId);
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
    }
}
