<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\GameResultRequest;
use App\Services\Game\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController
{
    public function __construct(
        private GameService $gameService,
    )
    {
    }

    public function setStatusInProgress(Request $request): void
    {
        $validated = $request->validate([
            'gameId' => 'required',
        ]);

        $this->gameService->setStatusInProgress($validated['gameId']);
    }

    public function update(GameResultRequest $request): JsonResponse
    {
        $dto = $request->toDTO();

        $success = $this->gameService->update($dto);

        return response()->json(['success' => $success]);
    }

    public function getActiveGames(Request $request): JsonResponse
    {
        $tournamentId = $request->query('tournamentId');

        $games = $this->gameService->getActiveGames($tournamentId);

        return response()->json($games);
    }
}









