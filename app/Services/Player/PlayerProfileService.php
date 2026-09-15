<?php

namespace App\Services\Player;

use App\Domain\Career\CareerWindow;
use App\Domain\FriendshipInvitationDomain;
use App\Domain\PlayerDomain;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Player\PlayerRepository;
use App\Services\Badge\CheckoutWheelAssembler;
use App\Services\Career\PlayerCareerStatsService;
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
        private PlayerCareerStatsService $playerCareerStatsService,
        private PlayerOverviewService $playerOverviewService,
        private CheckoutWheelAssembler $checkoutWheelAssembler,
    ) {}

    /**
     * Pełny payload profilu dla API mobile — ten sam rdzeń co web players.show.
     *
     * @return array<string, mixed>
     */
    public function buildProfile(Player $player, ?User $viewer): array
    {
        $assembled = $this->assembleProfile($player, $viewer);

        return [
            'player' => [
                'id' => $assembled['player']->id,
                'userId' => (int) $assembled['player']->user_id,
                'name' => $assembled['player']->name,
                'description' => $assembled['player']->description,
                'registeredAt' => $assembled['player']->user?->created_at?->format('d.m.Y'),
                'initials' => $assembled['player']->initials(),
            ],
            'friendship' => $this->mapFriendshipForApi($assembled['friendship']),
            'quickStats' => $assembled['quickStats'],
            'tournamentStats' => $assembled['tournamentStats'],
            'gameHistory' => [
                'items' => $assembled['historyItems'],
                'hasMore' => $assembled['historyHasMore'],
            ],
            'liveGames' => $assembled['liveGames'],
            'career' => $assembled['career'],
            'overviewSplit' => $assembled['overviewSplit'],
            'overview' => $assembled['overview'],
            'checkoutHits' => $assembled['checkoutHits'],
            'checkoutItems' => $assembled['checkoutItems'],
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
    public function buildGameHistoryPage(Player $player, int $page, ?User $viewer = null): array
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        return $this->playerGameHistoryService->getHistoryPage(
            $player->id,
            $page,
            $this->isSelf($player, $viewer),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCareer(Player $player, ?User $viewer, ?string $window, ?string $source): array
    {
        return $this->playerCareerStatsService->build($player, $viewer, $window, $source);
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
     *     liveGames: array,
     *     overviewSplit: array{window: string, quick: array<string, mixed>, tournament: array<string, mixed>},
     *     overview: array<string, mixed>,
     *     checkoutHits: array<int, int>,
     *     checkoutItems: list<array{key: string, timesEarned: int, level: int, levelName: string}>
     * }
     */
    public function buildWebShow(Player $player, ?User $viewer): array
    {
        $assembled = $this->assembleProfile($player, $viewer);
        $friendship = $assembled['friendship'];

        return [
            'player' => $assembled['player'],
            'quickStats' => $assembled['quickStats'],
            'tournamentStats' => $assembled['tournamentStats'],
            'isOwnProfile' => $friendship['isSelf'],
            'isFriend' => $friendship['isFriend'],
            'canInviteFriend' => $friendship['canInvite'],
            'pendingSentInvitation' => $friendship['pendingSent'],
            'pendingReceivedInvitation' => $friendship['pendingReceived'],
            'gameHistoryItems' => $assembled['historyItems'],
            'gameHistoryHasMore' => $assembled['historyHasMore'],
            'liveGames' => $assembled['liveGames'],
            'career' => $assembled['career'],
            'overviewSplit' => $assembled['overviewSplit'],
            'overview' => $assembled['overview'],
            'checkoutHits' => $assembled['checkoutHits'],
            'checkoutItems' => $assembled['checkoutItems'],
        ];
    }

    /**
     * Jedno złożenie danych profilu — web i API tylko mapują kształt.
     *
     * @return array{
     *     player: Player,
     *     quickStats: array<string, mixed>,
     *     tournamentStats: array<string, mixed>,
     *     historyItems: array,
     *     historyHasMore: bool,
     *     friendship: array{
     *         isSelf: bool,
     *         isFriend: bool,
     *         canInvite: bool,
     *         pendingSent: FriendshipInvitationDomain|null,
     *         pendingReceived: FriendshipInvitationDomain|null
     *     },
     *     liveGames: list<array<string, mixed>>,
     *     career: array<string, mixed>,
     *     overviewSplit: array{window: string, quick: array<string, mixed>, tournament: array<string, mixed>},
     *     overview: array<string, mixed>,
     *     checkoutHits: array<int, int>,
     *     checkoutItems: list<array{key: string, timesEarned: int, level: int, levelName: string}>
     * }
     */
    private function assembleProfile(Player $player, ?User $viewer): array
    {
        $isSelf = $this->isSelf($player, $viewer);
        $core = $this->prepareRegisteredProfile($player, $isSelf);
        $checkoutItems = $this->checkoutWheelAssembler->itemsForPlayer((int) $player->id);
        $checkoutHits = [];
        foreach ($checkoutItems as $item) {
            $times = (int) $item['timesEarned'];
            if ($times > 0) {
                $checkoutHits[(int) $item['key']] = $times;
            }
        }

        return [
            'player' => $player,
            'quickStats' => $core['quickStats'],
            'tournamentStats' => $core['tournamentStats'],
            'historyItems' => $core['historyItems'],
            'historyHasMore' => $core['historyHasMore'],
            'friendship' => $this->resolveFriendshipState($player, $viewer),
            'liveGames' => $this->playerLiveGameService->findLiveGamesForPlayer((int) $player->id),
            'career' => $this->playerCareerStatsService->build($player, $viewer, CareerWindow::DEFAULT_KEY, 'all'),
            'overviewSplit' => $this->playerCareerStatsService->buildOverviewSplit($player),
            'overview' => $this->playerOverviewService->forProfile($player),
            'checkoutHits' => $checkoutHits,
            'checkoutItems' => $checkoutItems,
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
    private function prepareRegisteredProfile(Player $player, bool $includeTraining): array
    {
        if (! $player->user_id) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }

        $player->loadMissing('user');
        $this->playerStatsService->recalculateAndSave($player->id);

        $historyFirstPage = $this->playerGameHistoryService->getHistoryPage($player->id, 1, $includeTraining);

        return [
            'quickStats' => $this->playerStatsService->getStoredQuickStats($player),
            'tournamentStats' => $this->playerStatsService->getStoredTournamentStats($player),
            'historyItems' => $historyFirstPage['items'],
            'historyHasMore' => (bool) $historyFirstPage['has_more'],
        ];
    }

    private function isSelf(Player $player, ?User $viewer): bool
    {
        return $viewer?->player !== null && (int) $viewer->player->id === (int) $player->id;
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
