<?php

namespace App\Http\Controllers\Api\GameScoring;

use App\DTO\GameScoring\CloseLegPlayerStatsDTO;
use App\DTO\GameScoring\RecordVisitDTO;
use App\Http\Controllers\Controller;
use App\Services\GameScoring\GameScoringService;
use App\Services\League\LeagueGamePlayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueGameScoringController extends Controller
{
    public function __construct(
        private GameScoringService $gameScoringService,
        private LeagueGamePlayService $leagueGamePlayService,
    ) {}

    public function state(Request $request, int $leagueGame): JsonResponse
    {
        return $this->respond($request, $leagueGame, mutate: false, run: function ($context, $game) {
            return $this->gameScoringService->getState($context, $game);
        });
    }

    public function startLeg(Request $request, int $leagueGame): JsonResponse
    {
        $validated = $request->validate([
            'player1DoubleTracked' => 'required|boolean',
            'player2DoubleTracked' => 'required|boolean',
        ]);

        return $this->respond($request, $leagueGame, mutate: true, run: function ($context, $game) use ($validated) {
            return $this->gameScoringService->startLeg(
                $context,
                $game,
                $validated['player1DoubleTracked'],
                $validated['player2DoubleTracked'],
            );
        });
    }

    public function recordVisit(Request $request, int $leagueGame, int $leg): JsonResponse
    {
        $validated = $request->validate(RecordVisitDTO::validationRules());

        return $this->respond($request, $leagueGame, mutate: true, run: function ($context, $game) use ($validated, $leg) {
            return $this->gameScoringService->recordVisit(
                $context,
                $game,
                $leg,
                RecordVisitDTO::fromArray($validated),
            );
        });
    }

    public function undoVisit(Request $request, int $leagueGame, int $leg): JsonResponse
    {
        return $this->respond($request, $leagueGame, mutate: true, run: function ($context, $game) use ($leg) {
            return $this->gameScoringService->undoLastVisit($context, $game, $leg);
        });
    }

    public function closeLeg(Request $request, int $leagueGame, int $leg): JsonResponse
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
            'reason' => 'nullable|string|in:checkout,bull_off,loss_threshold',
        ]);

        $playerStats = array_map(
            fn (array $row) => CloseLegPlayerStatsDTO::fromArray($row),
            $validated['players'],
        );

        return $this->respond($request, $leagueGame, mutate: true, run: function ($context, $game) use ($validated, $leg, $playerStats) {
            return $this->gameScoringService->closeLeg(
                $context,
                $game,
                $leg,
                (int) $validated['winnerId'],
                $playerStats,
                (string) ($validated['reason'] ?? 'checkout'),
            );
        });
    }

    private function respond(Request $request, int $leagueGameId, bool $mutate, callable $run): JsonResponse
    {
        [$context, $game] = $this->gameScoringService->resolveLeagueGame($leagueGameId);
        if ($mutate) {
            $this->leagueGamePlayService->assertCanScore($request->user(), $game);
        } else {
            $this->leagueGamePlayService->assertCanViewScoring($request->user(), $game);
        }

        return response()->json($run($context, $game));
    }
}
