<?php

namespace App\Http\Controllers\Api;

use App\DTO\QuickGame\QuickGameResultDTO;
use App\DTO\QuickGame\QuickGameDTO;
use App\Http\Requests\QuickGameResultRequest;
use App\Repositories\Player\PlayerRepository;
use App\Services\QuickGame\QuickGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickGameController
{
    public function __construct(
        private QuickGameService $quickGameService,
        private PlayerRepository $playerRepository
    )
    {
    }

    /**
     * Tworzy szybki mecz między dwoma zarejestrowanymi graczami
     * POST /api/quick-match/create
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player1Id' => 'required|integer|exists:players,id',
            'player2Id' => 'required|integer|exists:players,id',
            'legsCount' => 'nullable|integer|min:1|max:15',
        ]);

        $userId = $request->user()->id;
        $dto = QuickGameDTO::fromArray($validated);

        try {
            $gameId = $this->quickGameService->createQuickGame(
                $dto->player1Id,
                $dto->player2Id,
                $userId,
                (int) ($validated['legsCount'] ?? 3),
            );

            return response()->json([
                'message' => 'Szybki mecz został utworzony',
                'gameId' => $gameId
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Pobiera aktywne szybkie mecze użytkownika
     * GET /api/quick-match/active
     */
    public function getActive(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        // Pobierz gracza użytkownika
        $player = $this->playerRepository->findByUserId($userId);
        
        if (!$player) {
            return response()->json([
                'games' => []
            ]);
        }

        $games = $this->quickGameService->getActiveForUser($userId);

        return response()->json([
            'games' => $games->map(function ($game) {
                return [
                    'id' => $game->id,
                    'type' => 'quick_match',
                    'player1' => [
                        'id' => $game->player1->id,
                        'name' => $game->player1->name,
                    ],
                    'player2' => [
                        'id' => $game->player2->id,
                        'name' => $game->player2->name,
                    ],
                ];
            })
        ]);
    }

    /**
     * Ustawia status szybkiego meczu na "w trakcie"
     * POST /api/quick-match/inProgress
     */
    public function setStatusInProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gameId' => 'required|integer',
        ]);

        $this->quickGameService->setStatusInProgress($validated['gameId']);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/quick-game/update
     * Lobby: players[] bez gameId. Po scoring API: gameId + achievements (opcjonalnie players).
     */
    public function update(QuickGameResultRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dto = QuickGameResultDTO::fromArray($validated);

        try {
            $this->quickGameService->finishFromMobile(
                $dto,
                isset($validated['lobbyId']) ? (int) $validated['lobbyId'] : null,
                isset($validated['gameId']) ? (int) $validated['gameId'] : null,
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}










