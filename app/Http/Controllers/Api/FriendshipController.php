<?php

namespace App\Http\Controllers\Api;

use App\Services\FriendshipService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController
{
    public function __construct(
        private FriendshipService $friendshipService,
        private UserService $userService
    )
    {
    }

    /**
     * Dodaje znajomego
     * POST /api/friends/add
     */
    public function addFriend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'friendId' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->user()->id;
        $friendId = $validated['friendId'];

        // Nie można dodać siebie jako znajomego
        if ($userId === $friendId) {
            return response()->json([
                'message' => 'Nie możesz dodać siebie jako znajomego'
            ], 400);
        }

        $this->friendshipService->addFriend($userId, $friendId);

        return response()->json([
            'message' => 'Znajomy został dodany'
        ], 201);
    }

    /**
     * Usuwa znajomego
     * DELETE /api/friends/remove
     */
    public function removeFriend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'friendId' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->user()->id;
        $friendId = $validated['friendId'];

        $this->friendshipService->removeFriend($userId, $friendId);

        return response()->json([
            'message' => 'Znajomy został usunięty'
        ]);
    }

    /**
     * Pobiera listę znajomych
     * GET /api/friends
     */
    public function getFriends(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $friends = $this->friendshipService->getFriends($userId);

        return response()->json([
            'friends' => $friends->map(function ($friendship) {
                return [
                    'id' => $friendship->friendId,
                    'name' => $friendship->friendPlayer->name,
                    'playerId' => $friendship->friendPlayer->id,
                ];
            })
        ]);
    }

    /**
     * Wyszukuje użytkowników po nazwie gracza
     * GET /api/users/search?q=...
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:50',
        ]);

        $userId = $request->user()->id;
        $searchTerm = $validated['q'];
        $limit = $request->input('limit', 20);

        $users = $this->userService->searchByPlayerName($searchTerm, $userId, $limit);

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['player']?->name ?? 'Brak nazwy',
                    'playerId' => $user['player']?->id ?? null,
                ];
            })
        ]);
    }
}
