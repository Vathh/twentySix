<?php

namespace App\Http\Controllers;

use App\Models\Player\Player;
use App\Services\Friends\FriendshipService;
use App\Services\Player\PlayerAvatarService;
use App\Services\Player\PlayerGameHistoryService;
use App\Services\Player\PlayerProfileService;
use App\Services\Player\PlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private PlayerGameHistoryService $playerGameHistoryService,
        private FriendshipService $friendshipService,
        private PlayerProfileService $playerProfileService,
        private PlayerService $playerService,
        private PlayerAvatarService $playerAvatarService,
    ) {}

    public function search(Request $request): View|JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $players = $this->searchHits($this->playerService->searchRegisteredByName($q));

        if ($request->expectsJson()) {
            return response()->json(['players' => $players]);
        }

        return view('players.search', [
            'q' => $q,
            'players' => $players,
        ]);
    }

    /**
     * @param  Collection<int, Player>  $players
     * @return list<array{id: int, name: string, initials: string, avatarUrl: string|null, url: string}>
     */
    private function searchHits(Collection $players): array
    {
        return $players->map(fn (Player $player) => [
            'id' => (int) $player->id,
            'name' => $player->name,
            'initials' => $player->initials(),
            'avatarUrl' => $player->avatarUrl(),
            'url' => route('players.show', $player),
        ])->values()->all();
    }

    public function show(Player $player): View|RedirectResponse
    {
        return view('players.show', $this->playerProfileService->buildWebShow(
            $player,
            Auth::user(),
        ));
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
        $file = $request->file('avatar');
        if ($file !== null) {
            $this->playerAvatarService->validateUpload($file);
        }

        $updated = $this->playerProfileService->updateOwnProfile($player, Auth::user(), $request->all());

        if ($file !== null) {
            $this->playerAvatarService->storeForOwner($updated, Auth::user(), $file);
        } elseif ($request->boolean('remove_avatar')) {
            $this->playerAvatarService->removeForOwner($updated, Auth::user());
        }

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
        $data = $this->playerGameHistoryService->getHistoryPage(
            $player->id,
            $page,
            Auth::user()?->player !== null && (int) Auth::user()->player->id === (int) $player->id,
        );

        return response()->json($data);
    }

    public function career(Request $request, Player $player): JsonResponse
    {
        try {
            return response()->json(
                $this->playerProfileService->buildCareer(
                    $player,
                    Auth::user(),
                    $request->query('window'),
                    $request->query('source'),
                ),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
