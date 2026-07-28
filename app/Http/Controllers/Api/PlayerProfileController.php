<?php

namespace App\Http\Controllers\Api;

use App\Models\Player\Player;
use App\Services\Player\PlayerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController
{
    public function __construct(
        private PlayerProfileService $playerProfileService,
    ) {
    }

    /**
     * GET /api/players/{player}
     */
    public function show(Request $request, Player $player): JsonResponse
    {
        return response()->json(
            $this->playerProfileService->buildProfile($player, $request->user()),
        );
    }

    /**
     * GET /api/players/{player}/games?page=
     */
    public function games(Request $request, Player $player): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->playerProfileService->buildGameHistoryPage($player, $page),
        );
    }
}
