<?php

namespace App\Http\Controllers\Api;

use App\Models\League\League;
use App\Services\League\LeagueService;
use Illuminate\Http\JsonResponse;

class LeagueCatalogController
{
    public function __construct(
        private LeagueService $leagueService,
    ) {
    }

    /**
     * GET /api/leagues/{league}
     */
    public function show(League $league): JsonResponse
    {
        return response()->json($this->leagueService->showForApi($league->id));
    }
}
