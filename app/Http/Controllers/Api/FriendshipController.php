<?php

namespace App\Http\Controllers\Api;

use App\Services\Friends\FriendshipService;
use App\Services\User\UserService;
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

    /**
     * Wysyła zaproszenie do znajomych
     * POST /api/friends/invite
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiverId' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->user()->id;
        $receiverId = $validated['receiverId'];

        // Nie można wysłać zaproszenia do siebie
        if ($userId === $receiverId) {
            return response()->json([
                'message' => 'Nie możesz wysłać zaproszenia do siebie'
            ], 400);
        }

        try {
            $invitation = $this->friendshipService->sendInvitation($userId, $receiverId);

            return response()->json([
                'message' => 'Zaproszenie zostało wysłane',
                'invitation' => [
                    'id' => $invitation->id,
                    'sender' => [
                        'id' => $invitation->senderId,
                        'name' => $invitation->senderPlayer->name,
                        'playerId' => $invitation->senderPlayer->id,
                    ],
                    'receiver' => [
                        'id' => $invitation->receiverId,
                        'name' => $invitation->receiverPlayer?->name ?? 'Brak nazwy',
                        'playerId' => $invitation->receiverPlayer?->id ?? null,
                    ],
                    'status' => $invitation->status,
                ]
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Akceptuje zaproszenie do znajomych
     * POST /api/friends/accept
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitationId' => 'required|integer|exists:friendship_invitations,id',
        ]);

        $userId = $request->user()->id;

        try {
            $this->friendshipService->acceptInvitation($validated['invitationId'], $userId);

            return response()->json([
                'message' => 'Zaproszenie zostało zaakceptowane'
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Odrzuca zaproszenie do znajomych
     * POST /api/friends/reject
     */
    public function rejectInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitationId' => 'required|integer|exists:friendship_invitations,id',
        ]);

        $userId = $request->user()->id;

        try {
            $this->friendshipService->rejectInvitation($validated['invitationId'], $userId);

            return response()->json([
                'message' => 'Zaproszenie zostało odrzucone'
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Pobiera zaproszenia otrzymane przez użytkownika
     * GET /api/friends/invitations/received
     */
    public function getReceivedInvitations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = $this->friendshipService->getReceivedInvitations($userId);

        return response()->json([
            'invitations' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'sender' => [
                        'id' => $invitation->senderId,
                        'name' => $invitation->senderPlayer?->name ?? 'Brak nazwy',
                        'playerId' => $invitation->senderPlayer?->id ?? null,
                    ],
                    'status' => $invitation->status,
                    'createdAt' => $invitation->createdAt->toIso8601String(),
                ];
            })
        ]);
    }

    /**
     * Pobiera zaproszenia wysłane przez użytkownika
     * GET /api/friends/invitations/sent
     */
    public function getSentInvitations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $invitations = $this->friendshipService->getSentInvitations($userId);

        return response()->json([
            'invitations' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'receiver' => [
                        'id' => $invitation->receiverId,
                        'name' => $invitation->receiverPlayer?->name ?? 'Brak nazwy',
                        'playerId' => $invitation->receiverPlayer?->id ?? null,
                    ],
                    'status' => $invitation->status,
                    'createdAt' => $invitation->createdAt->toIso8601String(),
                ];
            })
        ]);
    }
}









