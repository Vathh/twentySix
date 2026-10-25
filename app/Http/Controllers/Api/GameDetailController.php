<?php

namespace App\Http\Controllers\Api;

use App\Services\GameScoring\GameDetailService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class GameDetailController
{
    public function __construct(
        private GameDetailService $gameDetailService,
    ) {}

    /**
     * GET /api/games/{type}/{id}
     * type: quick | group | playoff | league
     */
    public function show(string $type, int $id): JsonResponse
    {
        try {
            $payload = $this->gameDetailService->buildForApi(
                GameDetailService::kindFromRoute($type),
                $id,
            );
        } catch (DomainException) {
            return response()->json(['message' => 'Nieznany typ meczu.'], 404);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Nie znaleziono meczu.'], 404);
        }

        return response()->json($payload);
    }
}
