<?php

namespace App\Http\Controllers\Api;

use App\Services\GameScoring\GameDetailService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * GET /games/{type}/{id}/head-to-head?offset=5
     * GET /api/games/{type}/{id}/head-to-head?offset=5
     */
    public function headToHead(Request $request, string $type, int $id): JsonResponse
    {
        $offset = max(0, min(500, (int) $request->query('offset', 0)));

        try {
            $page = $this->gameDetailService->headToHeadRecent(
                GameDetailService::kindFromRoute($type),
                $id,
                $offset,
            );
        } catch (DomainException) {
            return response()->json(['message' => 'Nieznany typ meczu.'], 404);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Nie znaleziono meczu.'], 404);
        }

        return response()->json($page);
    }
}
