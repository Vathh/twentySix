<?php

namespace App\Services\QuickGame;

use App\Events\QuickGameLobbyUpdated;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameLobbyRepository;
use App\Repositories\QuickGame\QuickGameRepository;

class QuickGameLobbyService
{
    public const MAX_LOBBY_PLAYERS = 8;

    public const DEFAULT_LEGS_TO_WIN = 2;

    public function __construct(
        private QuickGameLobbyRepository $lobbyRepository,
        private PlayerRepository $playerRepository,
        private QuickGameRepository $quickGameRepository,
        private FriendshipRepository $friendshipRepository,
    ) {
    }

    /**
     * Tworzy nowe lobby
     *
     * @param  int  $hostUserId  ID użytkownika tworzącego lobby
     */
    public function create(int $hostUserId): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->create($hostUserId);

        $hostPlayer = $this->playerRepository->findByUserId($hostUserId);
        if ($hostPlayer) {
            $this->lobbyRepository->addPlayer($lobby->id, $hostPlayer->id, null, true);
        }

        $fresh = $lobby->fresh(['host.player', 'players.player']);
        $this->broadcastLobbyUpdated($fresh);

        return $fresh;
    }

    public function joinById(int $lobbyId, int $userId): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        $this->assertLobbyHasRoom($lobby);
        $this->assertRegisteredUserIsHostFriend($lobby, $userId);

        $player = $this->playerRepository->findByUserId($userId);
        if (! $player) {
            throw new \RuntimeException('Nie znaleziono gracza dla użytkownika');
        }

        if ((int) $lobby->host_id === $userId) {
            throw new \RuntimeException('Host jest już w lobby');
        }

        $alreadyInLobby = $lobby->players->contains('player_id', $player->id);
        if ($alreadyInLobby) {
            throw new \RuntimeException('Jesteś już w tym lobby');
        }

        if (! $this->lobbyRepository->hasPendingInvitation($lobbyId, $player->id)) {
            throw new \RuntimeException('Brak aktywnego zaproszenia do tego lobby');
        }

        $this->lobbyRepository->addPlayer($lobby->id, $player->id, null, true);
        $this->lobbyRepository->markInvitationAccepted($lobbyId, $player->id);

        $fresh = $lobby->fresh(['host.player', 'players.player']);
        $this->broadcastLobbyUpdated($fresh);

        return $fresh;
    }

    public function invite(int $lobbyId, int $hostUserId, int $invitedPlayerId): void
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może zapraszać do lobby');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        $alreadyInLobby = $lobby->players->contains('player_id', $invitedPlayerId);
        if ($alreadyInLobby) {
            throw new \RuntimeException('Ten gracz jest już w lobby');
        }

        if ($this->lobbyRepository->hasPendingInvitation($lobbyId, $invitedPlayerId)) {
            throw new \RuntimeException('Zaproszenie do tego gracza zostało już wysłane');
        }

        $this->assertLobbyHasRoom($lobby);

        $invited = $this->playerRepository->findById($invitedPlayerId);
        if (! $invited || $invited->userId === null) {
            throw new \RuntimeException('Nie znaleziono gracza');
        }
        if (! $this->friendshipRepository->areFriends($hostUserId, $invited->userId)) {
            throw new \RuntimeException('Do quick game można zapraszać tylko znajomych');
        }

        $this->lobbyRepository->createInvitation($lobbyId, $invitedPlayerId);
        $this->broadcastLobbyUpdatedById($lobbyId);
    }

    public function getPendingInvitationsForUser(int $userId): \Illuminate\Support\Collection
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (! $player) {
            return collect([]);
        }

        return $this->lobbyRepository->getPendingInvitationsForPlayer($player->id)
            ->map(function ($inv) {
                $lobby = $inv->lobby;
                $hostName = $lobby->host->player?->name ?? 'Host';

                return [
                    'id' => $inv->id,
                    'lobbyId' => $lobby->id,
                    'hostName' => $hostName,
                ];
            });
    }

    public function rejectInvitation(int $invitationId, int $userId): void
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (! $player) {
            throw new \RuntimeException('Nie znaleziono gracza');
        }
        $this->lobbyRepository->markInvitationRejected($invitationId, $player->id);
    }

    public function leave(int $lobbyId, ?int $userId = null, ?string $tempPlayerName = null): void
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        $playerId = null;
        if ($userId) {
            $player = $this->playerRepository->findByUserId($userId);
            $playerId = $player?->id;
        }

        $this->lobbyRepository->removePlayer($lobbyId, $playerId, $tempPlayerName);
        if (! ($userId && $lobby->host_id === $userId)) {
            $this->broadcastLobbyUpdatedById($lobbyId);
        }

        if ($userId && $lobby->host_id === $userId) {
            $this->lobbyRepository->delete($lobbyId);
        }
    }

    public function get(int $lobbyId): QuickGameLobby
    {
        return $this->lobbyRepository->find($lobbyId);
    }

    public function addGuest(int $lobbyId, int $hostUserId, string $tempPlayerName): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może dodawać gości do lobby');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie przyjmuje już graczy');
        }

        throw new \RuntimeException('W quick game MVP można grać tylko ze znajomymi — gracze tymczasowi są niedostępni');
    }

    public function setReady(int $lobbyId, int $userId, bool $isReady): QuickGameLobby
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (! $player) {
            throw new \RuntimeException('Nie znaleziono gracza');
        }

        $this->lobbyRepository->setPlayerReady($lobbyId, $player->id, $isReady);

        $fresh = $this->lobbyRepository->find($lobbyId);
        $this->broadcastLobbyUpdated($fresh);

        return $fresh;
    }

    public function startGame(
        int $lobbyId,
        int $hostUserId,
        ?int $legsCount = null,
        ?string $gameType = null,
        ?string $scoringMode = null,
        ?array $playerOrderIds = null
    ): QuickGameLobby {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może rozpocząć mecz');
        }

        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Lobby nie jest gotowe do rozpoczęcia');
        }

        $playersCount = $lobby->players()->count();
        if ($playersCount < 2) {
            throw new \RuntimeException('Musi być co najmniej 2 graczy');
        }

        $hostPlayerId = $lobby->host->player?->id;
        foreach ($lobby->players as $lp) {
            if ($lp->player_id === null) {
                continue;
            }
            if ($lp->player_id === $hostPlayerId) {
                continue;
            }
            if (! $lp->is_ready) {
                throw new \RuntimeException('Wszyscy zarejestrowani gracze muszą potwierdzić gotowość');
            }
        }

        $legs = $legsCount ?? $lobby->legs_count ?? self::DEFAULT_LEGS_TO_WIN;
        $game = $gameType ?? $lobby->game_type ?? '501';
        $mode = $scoringMode ?? $lobby->scoring_mode ?? 'each_own';

        $players = $lobby->players->values();
        $defaultOrderIds = $players->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $finalOrderIds = $defaultOrderIds;
        if (is_array($playerOrderIds) && count($playerOrderIds) > 0) {
            $requested = array_values(array_unique(array_map('intval', $playerOrderIds)));
            $allowed = array_flip($defaultOrderIds);
            $requestedFiltered = array_values(array_filter($requested, fn ($id) => isset($allowed[$id])));
            $missing = array_values(array_filter($defaultOrderIds, fn ($id) => ! in_array($id, $requestedFiltered, true)));
            $finalOrderIds = array_values(array_merge($requestedFiltered, $missing));
        }

        $lobby = $this->lobbyRepository->startGame($lobbyId, $legs, $game, $mode);

        $quickGameId = $this->createLiveQuickGameIfEligible($lobby, $legs, $finalOrderIds);
        $this->lobbyRepository->attachGameMeta($lobbyId, $quickGameId, $finalOrderIds);

        $lobby = $this->lobbyRepository->find($lobbyId);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    public function updateSettings(int $lobbyId, int $hostUserId, ?int $legsCount = null, ?string $gameType = null): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->updateSettings($lobbyId, $hostUserId, $legsCount, $gameType);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    public function updateScoringMode(int $lobbyId, int $hostUserId, string $scoringMode): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->updateScoringMode($lobbyId, $hostUserId, $scoringMode);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    private function broadcastLobbyUpdatedById(int $lobbyId): void
    {
        $this->broadcastLobbyUpdated($this->lobbyRepository->find($lobbyId));
    }

    private function broadcastLobbyUpdated(QuickGameLobby $lobby): void
    {
        broadcast(new QuickGameLobbyUpdated($lobby));
    }

    private function createLiveQuickGameIfEligible(QuickGameLobby $lobby, int $legsCount, array $playerOrderLobbyPlayerIds = []): ?int
    {
        $lobby->loadMissing('players');
        if ($lobby->players->count() !== 2) {
            return null;
        }

        $registered = $lobby->players->filter(fn ($p) => $p->player_id !== null)->values();
        if ($registered->count() !== 2) {
            return null;
        }

        $ordered = $registered;
        if (count($playerOrderLobbyPlayerIds) >= 2) {
            $byLobbyPlayerId = $registered->keyBy('id');
            $sorted = collect($playerOrderLobbyPlayerIds)
                ->map(fn ($lpId) => $byLobbyPlayerId->get((int) $lpId))
                ->filter()
                ->values();
            if ($sorted->count() === 2) {
                $ordered = $sorted;
            }
        }

        $quickGameId = $this->quickGameRepository->create(
            (int) $ordered[0]->player_id,
            (int) $ordered[1]->player_id,
            $legsCount,
            $lobby->id,
        );
        $this->quickGameRepository->setStatusInProgress($quickGameId);

        return $quickGameId;
    }

    private function assertLobbyHasRoom(QuickGameLobby $lobby): void
    {
        if ($lobby->players()->count() >= self::MAX_LOBBY_PLAYERS) {
            throw new \RuntimeException('W lobby może być maksymalnie '.self::MAX_LOBBY_PLAYERS.' graczy');
        }
    }

    private function assertRegisteredUserIsHostFriend(QuickGameLobby $lobby, int $userId): void
    {
        if ((int) $lobby->host_id === $userId) {
            return;
        }

        if (! $this->friendshipRepository->areFriends((int) $lobby->host_id, $userId)) {
            throw new \RuntimeException('Do quick game można dołączyć tylko jako znajomy hosta');
        }
    }
}
