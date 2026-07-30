<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\League\League;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\League\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController
{
    use MapsIndexPageForApi;

    public function __construct(
        private LeagueService $leagueService,
        private CompetitionShowSerializer $showSerializer,
    ) {
    }

    /**
     * GET /api/leagues?page=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->indexPageWithoutUrls($this->leagueService->getIndexPage($page)),
        );
    }

    /**
     * GET /api/leagues/{league}
     */
    public function show(League $league): JsonResponse
    {
        return response()->json($this->showSerializer->league($league));
    }
}
