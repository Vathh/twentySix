<?php

namespace App\Http\Controllers\Api;

use App\DTO\QuickGame\QuickGameResultDTO;
use App\Http\Requests\QuickGameResultRequest;
use App\Services\QuickGame\QuickGameService;
use Illuminate\Http\JsonResponse;

class QuickGameController
{
    public function __construct(
        private QuickGameService $quickGameService,
    ) {
    }

    /**
     * POST /api/quick-game/update
     * Po zakończeniu meczu FFA online: gameId + achievementy (wynik zapisuje silnik FFA).
     */
    public function update(QuickGameResultRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dto = QuickGameResultDTO::fromArray($validated);

        try {
            $this->quickGameService->attachAchievements((int) $validated['gameId'], $dto->achievements);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
