<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Season\Season;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\Season\SeasonService;
use App\Support\Catalog\CatalogSort;
use App\Support\Catalog\SeasonCatalogStatus;
use App\Support\Text\PolishFold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SeasonController
{
    use MapsIndexPageForApi;

    public function __construct(
        private SeasonService $seasonService,
        private CompetitionShowSerializer $showSerializer,
    ) {}

    /**
     * GET /api/seasons?page=&q=&sort=&status=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = PolishFold::term($request->query('q'));
        $sort = CatalogSort::from($request->query('sort'));
        $status = SeasonCatalogStatus::from($request->query('status'));

        return response()->json(
            $this->indexPageWithoutUrls($this->seasonService->getIndexPage($page, $search, $sort, $status)),
        );
    }

    /**
     * GET /api/seasons/{season}
     */
    public function show(Season $season): JsonResponse
    {
        $payload = $this->showSerializer->season($season);
        $payload['canManage'] = Gate::allows('update', $season);

        return response()->json($payload);
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
