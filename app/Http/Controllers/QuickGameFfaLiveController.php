<?php

namespace App\Http\Controllers;

use App\Services\QuickGame\QuickGameFfaLiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuickGameFfaLiveController extends Controller
{
    public function __construct(
        private QuickGameFfaLiveService $liveService,
    ) {
    }

    public function live(int $lobbyId): View|RedirectResponse
    {
        $page = $this->liveService->buildLivePage($lobbyId);

        if ($page['finished']) {
            if (! empty($page['showUrl'])) {
                return redirect($page['showUrl']);
            }

            return redirect()
                ->route('pages.home')
                ->with('success', 'Mecz FFA został zakończony.');
        }

        return view('quick-game.ffa-live', [
            'lobbyId' => $page['lobbyId'],
            'initialState' => $page['initialState'],
            'liveStateUrl' => $page['liveStateUrl'],
            'showUrl' => $page['showUrl'],
            'broadcastChannel' => $page['broadcastChannel'],
            'formatLabel' => $page['formatLabel'],
            'reverb' => $page['reverb'],
        ]);
    }

    public function liveState(int $lobbyId): JsonResponse
    {
        $result = $this->liveService->buildLiveState($lobbyId);

        if ($result['finished']) {
            return response()->json([
                'message' => $result['message'] ?? 'Mecz zakończony.',
                'showUrl' => $result['showUrl'],
            ], 410);
        }

        return response()->json($result['state']);
    }
}
