<?php

namespace App\Services\Player;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Friends\FriendshipService;

class PlayerProfileService
{
    public function __construct(
        private PlayerStatsService $playerStatsService,
        private PlayerGameHistoryService $playerGameHistoryService,
        private FriendshipService $friendshipService,
    ) {
    }

    /**
     * Pełny payload profilu dla API mobile (odpowiednik web players.show).
     *
     * @return array{
     *     player: array{id: int, userId: int, name: string, registeredAt: string|null},
     *     friendship: array{
     *         isSelf: bool,
     *         isFriend: bool,
     *         canInvite: bool,
     *         pendingSent: bool,
     *         pendingReceived: array{id: int}|null
     *     },
     *     quickStats: array<string, mixed>,
     *     tournamentStats: array<string, mixed>,
     *     gameHistory: array{items: array, hasMore: bool}
     * }
     */
    public function buildProfile(Player $player, ?User $viewer): array
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        $player->loadMissing('user');
        $this->playerStatsService->recalculateAndSave($player->id);

        $historyFirstPage = $this->playerGameHistoryService->getHistoryPage($player->id, 1);

        return [
            'player' => [
                'id' => $player->id,
                'userId' => (int) $player->user_id,
                'name' => $player->name,
                'registeredAt' => $player->user?->created_at?->format('d.m.Y'),
            ],
            'friendship' => $this->buildFriendship($player, $viewer),
            'quickStats' => $this->playerStatsService->getStoredQuickStats($player),
            'tournamentStats' => $this->playerStatsService->getStoredTournamentStats($player),
            'gameHistory' => [
                'items' => $historyFirstPage['items'],
                'hasMore' => (bool) $historyFirstPage['has_more'],
            ],
        ];
    }

    /**
     * @return array{items: array, has_more: bool}
     */
    public function buildGameHistoryPage(Player $player, int $page): array
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        return $this->playerGameHistoryService->getHistoryPage($player->id, $page);
    }

    /**
     * @return array{
     *     isSelf: bool,
     *     isFriend: bool,
     *     canInvite: bool,
     *     pendingSent: bool,
     *     pendingReceived: array{id: int}|null
     * }
     */
    private function buildFriendship(Player $player, ?User $viewer): array
    {
        $isSelf = false;
        $isFriend = false;
        $canInvite = false;
        $pendingSent = false;
        $pendingReceived = null;

        if ($viewer === null) {
            return [
                'isSelf' => false,
                'isFriend' => false,
                'canInvite' => false,
                'pendingSent' => false,
                'pendingReceived' => null,
            ];
        }

        $viewerPlayer = $viewer->player;
        $profileUserId = (int) $player->user_id;
        $viewerUserId = (int) $viewer->id;
        $isSelf = $viewerPlayer !== null && (int) $viewerPlayer->id === (int) $player->id;

        if ($viewerPlayer && $profileUserId > 0) {
            $isFriend = $this->friendshipService->areFriends($viewerUserId, $profileUserId);
            $sent = $this->friendshipService->findPendingInvitation($viewerUserId, $profileUserId);
            $received = $this->friendshipService->findPendingInvitation($profileUserId, $viewerUserId);
            $pendingSent = $sent !== null;
            $pendingReceived = $received !== null ? ['id' => $received->id] : null;
            $canInvite = ! $isFriend
                && ! $isSelf
                && $sent === null
                && $received === null;
        }

        return [
            'isSelf' => $isSelf,
            'isFriend' => $isFriend,
            'canInvite' => $canInvite,
            'pendingSent' => $pendingSent,
            'pendingReceived' => $pendingReceived,
        ];
    }
}
