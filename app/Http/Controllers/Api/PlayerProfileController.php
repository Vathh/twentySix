<?php

namespace App\Http\Controllers\Api;

use App\Models\Player\Player;
use App\Services\Player\PlayerAvatarService;
use App\Services\Player\PlayerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerProfileController
{
    public function __construct(
        private PlayerProfileService $playerProfileService,
        private PlayerAvatarService $playerAvatarService,
    ) {}

    /**
     * GET /api/players/{player}
     */
    public function show(Request $request, Player $player): JsonResponse
    {
        return response()->json(
            $this->playerProfileService->buildProfile($player, $request->user()),
        );
    }

    /**
     * GET /api/players/{player}/games?page=
     */
    public function games(Request $request, Player $player): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $this->playerProfileService->buildGameHistoryPage($player, $page, $request->user()),
        );
    }

    /**
     * PUT /api/players/{player}
     */
    public function update(Request $request, Player $player): JsonResponse
    {
        $updated = $this->playerProfileService->updateOwnProfile(
            $player,
            $request->user(),
            $request->all(),
        );

        return response()->json([
            'message' => 'Profil został zaktualizowany.',
            'player' => [
                'id' => $updated->id,
                'userId' => (int) $updated->user_id,
                'name' => $updated->name,
                'description' => $updated->description,
                'initials' => $updated->initials(),
                'avatarUrl' => $updated->avatarUrl(),
            ],
        ]);
    }

    /**
     * POST /api/players/{player}/avatar
     */
    public function storeAvatar(Request $request, Player $player): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [], [
            'avatar' => 'zdjęcie',
        ]);

        $updated = $this->playerAvatarService->storeForOwner(
            $player,
            $request->user(),
            $request->file('avatar'),
        );

        return response()->json([
            'message' => 'Zdjęcie profilowe zostało zapisane.',
            'avatarUrl' => $updated->avatarUrl(),
        ]);
    }

    /**
     * DELETE /api/players/{player}/avatar
     */
    public function destroyAvatar(Request $request, Player $player): JsonResponse
    {
        $this->playerAvatarService->removeForOwner($player, $request->user());

        return response()->json([
            'message' => 'Zdjęcie profilowe zostało usunięte.',
            'avatarUrl' => null,
        ]);
    }
}
