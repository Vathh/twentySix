<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Tournament\Tournament;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\Tournament\TournamentService;
use App\Support\Text\PolishFold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentCatalogController
{
    use MapsIndexPageForApi;

    public function __construct(
        private TournamentService $tournamentService,
        private CompetitionShowSerializer $showSerializer,
    ) {}

    /**
     * GET /api/tournaments?page=&q=
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = PolishFold::term($request->query('q'));

        return response()->json(
            $this->indexPageWithoutUrls($this->tournamentService->getIndexPage($page, $search)),
        );
    }

    /**
     * GET /api/tournaments/{tournament}
     */
    public function show(Tournament $tournament): JsonResponse
    {
        return response()->json($this->showSerializer->tournament($tournament));
    }
}
