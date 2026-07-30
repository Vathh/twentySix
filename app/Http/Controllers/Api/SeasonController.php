<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Season\Season;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\Season\SeasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonController
{
    use MapsIndexPageForApi;

    public function __construct(
        private SeasonService $seasonService,
        private CompetitionShowSerializer $showSerializer,
    ) {
    }

    /**
     * GET /api/seasons?page=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->indexPageWithoutUrls($this->seasonService->getIndexPage($page)),
        );
    }

    /**
     * GET /api/seasons/{season}
     */
    public function show(Season $season): JsonResponse
    {
        return response()->json($this->showSerializer->season($season));
    }

    /**
     * GET /api/seasons/{season}/standings?page=
     */
    public function standings(Request $request, Season $season): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($this->showSerializer->seasonStandingsPage($season, $page));
    }
}
