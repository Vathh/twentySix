<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebcamMatchSignalRequest;
use App\Http\Requests\WebcamMatchVisitRequest;
use App\Services\Webcam\WebcamMatchService;
use App\Support\Broadcasting\ReverbClientConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class WebcamMatchController extends Controller
{
    public function __construct(
        private WebcamMatchService $matches,
    ) {}

    public function index(): View
    {
        return view('webcam.index', $this->matches->indexData((int) Auth::id()));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'friend_user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'friend_user_id.required' => 'Wybierz znajomego.',
            'friend_user_id.exists' => 'Nie znaleziono gracza.',
        ]);

        $match = $this->matches->openWithFriend((int) Auth::id(), (int) $validated['friend_user_id']);

        return redirect()
            ->route('webcam.show', $match)
            ->with('success', 'Mecz kamerkowy jest gotowy. Wybierz kamerki i oznacz się jako gotowy.');
    }

    public function show(int $match): View
    {
        $userId = (int) Auth::id();
        $state = $this->matches->stateFor($userId, $match);
        $opponent = collect($state['players'])->firstWhere('userId', '!=', $userId);

        return view('webcam.show', [
            'state' => $state,
            'config' => [
                'userId' => $userId,
                'opponentId' => (int) ($opponent['userId'] ?? 0),
                'matchId' => $match,
                'csrf' => csrf_token(),
                'iceServers' => config('webcam.ice_servers'),
                'reverb' => ReverbClientConfig::forWeb(),
                'authEndpoint' => route('webcam.broadcast.auth'),
                'stateUrl' => route('webcam.state', $match),
                'readyUrl' => route('webcam.ready', $match),
                'visitUrl' => route('webcam.visit', $match),
                'undoUrl' => route('webcam.undo', $match),
                'signalUrl' => route('webcam.signal', $match),
            ],
        ]);
    }

    public function state(int $match): JsonResponse
    {
        return response()->json($this->matches->stateFor((int) Auth::id(), $match));
    }

    public function ready(int $match): JsonResponse
    {
        return response()->json($this->matches->markReady((int) Auth::id(), $match));
    }

    public function visit(WebcamMatchVisitRequest $request, int $match): JsonResponse
    {
        return response()->json($this->matches->recordVisit(
            (int) $request->user()->id,
            $match,
            $request->validated(),
        ));
    }

    public function undo(int $match): JsonResponse
    {
        return response()->json($this->matches->undoLastVisit((int) Auth::id(), $match));
    }

    public function signal(WebcamMatchSignalRequest $request, int $match): JsonResponse
    {
        $this->matches->signal(
            (int) $request->user()->id,
            $match,
            (string) $request->validated('kind'),
            $request->validated('payload'),
        );

        return response()->json(['ok' => true]);
    }

    public function broadcastAuth(Request $request): mixed
    {
        return Broadcast::auth($request);
    }
}
