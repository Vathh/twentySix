<?php

namespace App\Http\Controllers;

use App\Services\Tournament\TournamentJoinRequestService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Publiczna strona po skanie QR — deep link do apki + kod tekstowy.
 */
class TournamentJoinLandingController
{
    public function __construct(
        private TournamentJoinRequestService $joinRequestService,
    ) {
    }

    public function show(Request $request, string $code): Factory|View
    {
        $tournament = $this->joinRequestService->findByJoinCode($code);
        $appDeepLink = 'twentysix://join-tournament/'.strtoupper(trim($code));

        return view('tournaments.join-landing', [
            'code' => strtoupper(trim($code)),
            'tournament' => $tournament,
            'appDeepLink' => $appDeepLink,
            'preview' => $tournament !== null
                ? $this->joinRequestService->previewForUser($code, $request->user()?->id)
                : null,
        ]);
    }
}
