<?php

namespace App\Http\Controllers;

use App\Models\Player\Player;
use App\Services\Friends\FriendshipService;
use App\Services\Player\PlayerGameHistoryService;
use App\Services\Player\PlayerStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private PlayerStatsService $playerStatsService,
        private PlayerGameHistoryService $playerGameHistoryService,
        private FriendshipService $friendshipService,
    ) {
    }

    public function search(Request $request): View
    {
        $q = $request->query('q', '');
        $players = collect();

        if ($q !== '') {
            $players = Player::query()
                ->whereNotNull('user_id')
                ->where('name', 'like', '%'.$q.'%')
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        return view('players.search', [
            'q' => $q,
            'players' => $players,
        ]);
    }

    public function show(Player $player): View|RedirectResponse
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }
        $player->load('user');
        $quickStats = $this->playerStatsService->getStoredQuickStats($player);
        $tournamentStats = $this->playerStatsService->getStoredTournamentStats($player);

        $isFriend = false;
        $canAddFriend = false;

        if (Auth::check()) {
            $viewerPlayer = Auth::user()->player;
            if ($viewerPlayer && $player->user_id) {
                $isFriend = $this->friendshipService->areFriends(Auth::id(), $player->user_id);
                $canAddFriend = ! $isFriend && $viewerPlayer->id !== $player->id;
            }
        }

        $historyFirstPage = $this->playerGameHistoryService->getHistoryPage($player->id, 1);

        return view('players.show', [
            'player' => $player,
            'quickStats' => $quickStats,
            'tournamentStats' => $tournamentStats,
            'isFriend' => $isFriend,
            'canAddFriend' => $canAddFriend,
            'gameHistoryItems' => $historyFirstPage['items'],
            'gameHistoryHasMore' => $historyFirstPage['has_more'],
        ]);
    }

    public function gameHistory(Request $request, Player $player): JsonResponse
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }
        $page = max(1, (int) $request->query('page', 1));
        $data = $this->playerGameHistoryService->getHistoryPage($player->id, $page);

        return response()->json($data);
    }

    public function addFriend(Request $request, Player $player): RedirectResponse
    {
        if (! $player->user_id) {
            return back()->with('error', 'Tego gracza nie można dodać do znajomych.');
        }

        if (Auth::user()->player && Auth::user()->player->id === $player->id) {
            return back()->with('error', 'Nie możesz dodać siebie do znajomych.');
        }

        try {
            $this->friendshipService->addFriend(Auth::id(), $player->user_id);

            return back()->with('success', 'Dodano do znajomych.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
