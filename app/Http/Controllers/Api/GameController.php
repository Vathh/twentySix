<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameType;
use App\Http\Requests\LockGameRequest;
use App\Http\Requests\GameResultRequest;
use App\Services\Game\GameService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController
{
    public function __construct(
        private GameService $gameService,
    )
    {
    }

    public function setStatusInProgress(LockGameRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->gameService->lockGame(
                (int) $validated['gameId'],
                GameType::from($validated['type']),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true]);
    }

    public function releaseLock(LockGameRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->gameService->releaseGameLock(
                (int) $validated['gameId'],
                GameType::from($validated['type']),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true]);
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
