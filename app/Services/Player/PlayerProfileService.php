<?php

namespace App\Services\Player;

use App\Domain\FriendshipInvitationDomain;
use App\Domain\PlayerDomain;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Player\PlayerRepository;
use App\Services\Friends\FriendshipService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlayerProfileService
{
    public function __construct(
        private PlayerStatsService $playerStatsService,
        private PlayerGameHistoryService $playerGameHistoryService,
        private FriendshipService $friendshipService,
        private PlayerLiveGameService $playerLiveGameService,
        private PlayerRepository $playerRepository,
    ) {
    }

    /**
     * Pełny payload profilu dla API mobile (odpowiednik web players.show).
     *
     * @return array{
     *     player: array{id: int, userId: int, name: string, description: string|null, registeredAt: string|null},
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
        $core = $this->prepareRegisteredProfile($player);
        $friendship = $this->resolveFriendshipState($player, $viewer);

        return [
            'player' => [
                'id' => $player->id,
                'userId' => (int) $player->user_id,
                'name' => $player->name,
                'description' => $player->description,
                'registeredAt' => $player->user?->created_at?->format('d.m.Y'),
            ],
            'friendship' => $this->mapFriendshipForApi($friendship),
            'quickStats' => $core['quickStats'],
            'tournamentStats' => $core['tournamentStats'],
            'gameHistory' => [
                'items' => $core['historyItems'],
                'hasMore' => $core['historyHasMore'],
            ],
        ];
    }

    /**
     * @param  array{description?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function updateOwnProfile(Player $player, User $actor, array $data): Player
    {
        $actorPlayer = $actor->player;
        if ($actorPlayer === null || (int) $actorPlayer->id !== (int) $player->id) {
            abort(403, 'Możesz edytować tylko swój profil.');
        }

        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        $validated = Validator::make($data, [
            'description' => ['nullable', 'string', 'max:'.PlayerDomain::DESCRIPTION_MAX_LENGTH],
        ])->validate();

        $description = array_key_exists('description', $validated)
            ? PlayerDomain::normalizeDescription($validated['description'])
            : null;

        return $this->playerRepository->updateDescription($player, $description);
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
     * Dane widoku web players.show — ten sam rdzeń co buildProfile(), plus modele pod formularze.
     *
     * @return array{
     *     player: Player,
     *     quickStats: array<string, mixed>,
     *     tournamentStats: array<string, mixed>,
     *     isOwnProfile: bool,
     *     isFriend: bool,
     *     canInviteFriend: bool,
     *     pendingSentInvitation: FriendshipInvitationDomain|null,
     *     pendingReceivedInvitation: FriendshipInvitationDomain|null,
     *     gameHistoryItems: array,
     *     gameHistoryHasMore: bool,
     *     liveGames: array
     * }
     */
    public function buildWebShow(Player $player, ?User $viewer): array
    {
        $core = $this->prepareRegisteredProfile($player);
        $friendship = $this->resolveFriendshipState($player, $viewer);

        return [
            'player' => $player,
            'quickStats' => $core['quickStats'],
            'tournamentStats' => $core['tournamentStats'],
            'isOwnProfile' => $friendship['isSelf'],
            'isFriend' => $friendship['isFriend'],
            'canInviteFriend' => $friendship['canInvite'],
            'pendingSentInvitation' => $friendship['pendingSent'],
            'pendingReceivedInvitation' => $friendship['pendingReceived'],
            'gameHistoryItems' => $core['historyItems'],
            'gameHistoryHasMore' => $core['historyHasMore'],
            'liveGames' => $this->playerLiveGameService->findLiveGamesForPlayer((int) $player->id),
        ];
    }

    /**
     * @return array{
     *     quickStats: array<string, mixed>,
     *     tournamentStats: array<string, mixed>,
     *     historyItems: array,
     *     historyHasMore: bool
     * }
     */
    private function prepareRegisteredProfile(Player $player): array
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        $player->loadMissing('user');
        $this->playerStatsService->recalculateAndSave($player->id);

        $historyFirstPage = $this->playerGameHistoryService->getHistoryPage($player->id, 1);

        return [
            'quickStats' => $this->playerStatsService->getStoredQuickStats($player),
            'tournamentStats' => $this->playerStatsService->getStoredTournamentStats($player),
            'historyItems' => $historyFirstPage['items'],
            'historyHasMore' => (bool) $historyFirstPage['has_more'],
        ];
    }

    /**
     * @param  array{
     *     isSelf: bool,
     *     isFriend: bool,
     *     canInvite: bool,
     *     pendingSent: FriendshipInvitationDomain|null,
     *     pendingReceived: FriendshipInvitationDomain|null
     * }  $state
     * @return array{
     *     isSelf: bool,
     *     isFriend: bool,
     *     canInvite: bool,
     *     pendingSent: bool,
     *     pendingReceived: array{id: int}|null
     * }
     */
    private function mapFriendshipForApi(array $state): array
    {
        return [
            'isSelf' => $state['isSelf'],
            'isFriend' => $state['isFriend'],
            'canInvite' => $state['canInvite'],
            'pendingSent' => $state['pendingSent'] !== null,
            'pendingReceived' => $state['pendingReceived'] !== null
                ? ['id' => $state['pendingReceived']->id]
                : null,
        ];
    }

    /**
     * @return array{
     *     isSelf: bool,
     *     isFriend: bool,
     *     canInvite: bool,
     *     pendingSent: FriendshipInvitationDomain|null,
     *     pendingReceived: FriendshipInvitationDomain|null
     * }
     */
    private function resolveFriendshipState(Player $player, ?User $viewer): array
    {
        if ($viewer === null) {
            return [
                'isSelf' => false,
                'isFriend' => false,
                'canInvite' => false,
                'pendingSent' => null,
                'pendingReceived' => null,
            ];
        }

        $viewerPlayer = $viewer->player;
        $profileUserId = (int) $player->user_id;
        $viewerUserId = (int) $viewer->id;
        $isSelf = $viewerPlayer !== null && (int) $viewerPlayer->id === (int) $player->id;

        $isFriend = false;
        $pendingSent = null;
        $pendingReceived = null;
        $canInvite = false;

        if ($viewerPlayer && $profileUserId > 0) {
            $isFriend = $this->friendshipService->areFriends($viewerUserId, $profileUserId);
            $pendingSent = $this->friendshipService->findPendingInvitation($viewerUserId, $profileUserId);
            $pendingReceived = $this->friendshipService->findPendingInvitation($profileUserId, $viewerUserId);
            $canInvite = FriendshipInvitationDomain::canInvite(
                isSelf: $isSelf,
                areFriends: $isFriend,
                hasPendingInvitation: $pendingSent !== null || $pendingReceived !== null,
            );
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
