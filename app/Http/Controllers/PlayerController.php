<?php

namespace App\Http\Controllers;

use App\Models\Player\Player;
use App\Services\Friends\FriendshipService;
use App\Services\Player\PlayerGameHistoryService;
use App\Services\Player\PlayerProfileService;
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
        private PlayerProfileService $playerProfileService,
    ) {
    }

    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
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
        $this->playerStatsService->recalculateAndSave($player->id);
        $quickStats = $this->playerStatsService->getStoredQuickStats($player);
        $tournamentStats = $this->playerStatsService->getStoredTournamentStats($player);

        $isFriend = false;
        $canInviteFriend = false;
        $pendingSentInvitation = null;
        $pendingReceivedInvitation = null;
        $isOwnProfile = false;

        if (Auth::check()) {
            $viewerPlayer = Auth::user()->player;
            $isOwnProfile = $viewerPlayer !== null && (int) $viewerPlayer->id === (int) $player->id;
            if ($viewerPlayer && $player->user_id) {
                $viewerUserId = (int) Auth::id();
                $profileUserId = (int) $player->user_id;
                $isFriend = $this->friendshipService->areFriends($viewerUserId, $profileUserId);
                $pendingSentInvitation = $this->friendshipService->findPendingInvitation(
                    $viewerUserId,
                    $profileUserId,
                );
                $pendingReceivedInvitation = $this->friendshipService->findPendingInvitation(
                    $profileUserId,
                    $viewerUserId,
                );
                $canInviteFriend = ! $isFriend
                    && ! $isOwnProfile
                    && $pendingSentInvitation === null
                    && $pendingReceivedInvitation === null;
            }
        }

        $historyFirstPage = $this->playerGameHistoryService->getHistoryPage($player->id, 1);

        return view('players.show', [
            'player' => $player,
            'quickStats' => $quickStats,
            'tournamentStats' => $tournamentStats,
            'isOwnProfile' => $isOwnProfile,
            'isFriend' => $isFriend,
            'canInviteFriend' => $canInviteFriend,
            'pendingSentInvitation' => $pendingSentInvitation,
            'pendingReceivedInvitation' => $pendingReceivedInvitation,
            'gameHistoryItems' => $historyFirstPage['items'],
            'gameHistoryHasMore' => $historyFirstPage['has_more'],
        ]);
    }

    public function edit(Player $player): View
    {
        $this->assertOwnsPlayer($player);

        return view('players.edit', [
            'player' => $player,
        ]);
    }

    public function update(Request $request, Player $player): RedirectResponse
    {
        $this->playerProfileService->updateOwnProfile($player, Auth::user(), $request->all());

        return redirect()
            ->route('players.show', $player)
            ->with('success', 'Profil został zaktualizowany.');
    }

    private function assertOwnsPlayer(Player $player): void
    {
        $viewerPlayer = Auth::user()?->player;
        if ($viewerPlayer === null || (int) $viewerPlayer->id !== (int) $player->id) {
            abort(403, 'Możesz edytować tylko swój profil.');
        }

        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }
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
            $this->friendshipService->sendInvitation((int) Auth::id(), (int) $player->user_id);

            return back()->with('success', 'Zaproszenie do znajomych zostało wysłane.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
