<?php

namespace App\Http\Controllers\Api;

use App\Models\League\LeagueSeason;
use App\Services\League\LeagueSeasonService;
use Illuminate\Http\JsonResponse;

class LeagueSeasonCatalogController
{
    public function __construct(
        private LeagueSeasonService $leagueSeasonService,
    ) {
    }

    /**
     * GET /api/league-seasons/{leagueSeason}
     */
    public function show(LeagueSeason $leagueSeason): JsonResponse
    {
        return response()->json($this->leagueSeasonService->showForApi($leagueSeason->id));
    }
}
