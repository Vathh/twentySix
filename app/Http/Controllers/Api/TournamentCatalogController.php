<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Api\Concerns\MapsIndexPageForApi;
use App\Models\Tournament\Tournament;
use App\Services\Competition\CompetitionShowSerializer;
use App\Services\GameScoring\GameAuthorizationService;
use App\Services\Tournament\LoginCodeService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentJoinRequestService;
use App\Services\Tournament\TournamentPlayoffBracketLiveService;
use App\Services\Tournament\TournamentService;
use App\Services\Tournament\TournamentStartPageService;
use App\Support\Text\PolishFold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentCatalogController
{
    use MapsIndexPageForApi;

    public function __construct(
        private TournamentService $tournamentService,
        private CompetitionShowSerializer $showSerializer,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
        private TournamentPlayoffBracketLiveService $playoffBracketLiveService,
        private GameAuthorizationService $gameAuthorizationService,
        private LoginCodeService $loginCodeService,
        private TournamentStartPageService $startPageService,
        private TournamentJoinRequestService $joinRequestService,
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
    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        $payload = $this->showSerializer->tournament($tournament);
        $payload['canManage'] = $this->gameAuthorizationService->canManageTournament($tournament);
        $payload['roster'] = null;
        $payload['join'] = null;
        if ($tournament->status === TournamentStatus::CREATED) {
            $payload['roster'] = $this->startPageService->publicRoster($tournament->id);
            $payload['join'] = $this->joinRequestService->statusForUser(
                $tournament,
                $request->user()?->id,
            );
        }
        $payload['tabletLogin'] = null;
        if ($payload['canManage'] && in_array($tournament->status, [TournamentStatus::GROUP, TournamentStatus::PLAYOFF], true)) {
            $code = $this->loginCodeService->getCodeForTournament($tournament->id);
            $payload['tabletLogin'] = $code === null ? null : [
                'code' => $code,
                'url' => $this->loginCodeService->loginUrl($code),
            ];
        }

        return response()->json($payload);
    }

    public function groupsLive(Tournament $tournament): JsonResponse
    {
        return response()->json($this->groupMatrixLiveService->snapshot($tournament->id));
    }

    public function playoffLive(Tournament $tournament): JsonResponse
    {
        return response()->json($this->playoffBracketLiveService->snapshot($tournament->id));
    }
}
