<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameType;
use App\Http\Requests\GameResultRequest;
use App\Http\Requests\LockGameRequest;
use App\Services\Game\GameService;
use App\Services\GameScoring\GameAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController
{
    public function __construct(
        private GameService $gameService,
        private GameAuthorizationService $gameAuthorizationService,
    ) {}

    public function setStatusInProgress(LockGameRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $gameId = (int) $validated['gameId'];
        $type = GameType::from($validated['type']);

        $this->gameAuthorizationService->assertLiveTournamentScoring(
            $request->user(),
            $this->gameService->tournamentIdForGame($gameId, $type),
        );

        $this->gameService->lockGame($gameId, $type);

        return response()->json(['success' => true]);
    }

    public function releaseLock(LockGameRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $gameId = (int) $validated['gameId'];
        $type = GameType::from($validated['type']);

        $this->gameAuthorizationService->assertLiveTournamentScoring(
            $request->user(),
            $this->gameService->tournamentIdForGame($gameId, $type),
        );

        $this->gameService->releaseGameLock($gameId, $type);

        return response()->json(['success' => true]);
    }

    /**
     * Aktualizacja wyniku turniejowego (grupa / playoff).
     *
     * Tryby (patrz GameService::update):
     * - mecz FINISHED + achievements → tylko zapis achievementów (mobile po scoring API);
     * - mecz SCHEDULED → legacy bulk finish (testy / fallback; produkcja kończy mecz przez scoring API).
     */
    public function update(GameResultRequest $request): JsonResponse
    {
        $dto = $request->toDTO();

        $this->gameAuthorizationService->assertLiveTournamentScoring(
            $request->user(),
            $this->gameService->tournamentIdForGame(
                $dto->gameResultDTO->gameId,
                $dto->gameResultDTO->type,
            ),
        );

        $success = $this->gameService->update($dto);

        return response()->json(['success' => $success]);
    }

    public function getActiveGames(Request $request): JsonResponse
    {
        $tournamentId = (int) $request->query('tournamentId');
        if ($tournamentId < 1) {
            return response()->json([]);
        }

        $games = $this->gameService->getActiveGames($tournamentId);

        return response()->json($games);
    }

    public function getRemainingGroups(Request $request): JsonResponse
    {
        $tournamentId = (int) $request->query('tournamentId');
        if ($tournamentId < 1) {
            return response()->json([]);
        }

        return response()->json($this->gameService->getRemainingGroups($tournamentId));
    }
}
