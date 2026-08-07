<?php

namespace App\Services\QuickGame;

use App\Events\QuickGameLobbyUpdated;
use App\Events\QuickGameRematchCreated;
use App\Events\QuickGameRematchIntentUpdated;
use App\Domain\PlayerDomain;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameLobbyRepository;
use App\Services\Push\InvitationPushService;
use App\Domain\GameScoring\MatchFormat;
use App\Support\QuickGameLobbyPayload;
use Illuminate\Support\Facades\DB;

class QuickGameLobbyService
{
    public const MAX_LOBBY_PLAYERS = 8;

    public function __construct(
        private QuickGameLobbyRepository $lobbyRepository,
        private PlayerRepository $playerRepository,
        private QuickGameFfaScoringService $ffaScoringService,
        private FriendshipRepository $friendshipRepository,
        private InvitationPushService $invitationPushService,
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

        $invitation = $this->lobbyRepository->createInvitation($lobbyId, $invitedPlayerId);
        $this->broadcastLobbyUpdatedById($lobbyId);

        $lobby->loadMissing('host.player');
        $this->invitationPushService->notifyLobbyInvitation(
            recipientUserId: $invited->userId,
            invitationId: (int) $invitation->id,
            hostName: $lobby->host->player?->name ?? 'Host',
        );
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

        $this->assertLobbyHasRoom($lobby);

        $name = trim($tempPlayerName);
        if ($name === '') {
            throw new \RuntimeException('Podaj imię gracza tymczasowego');
        }

        $this->assertGuestNameAvailableInLobby($lobby, $name);

        $guest = $this->playerRepository->createQuickGameGuest($name);
        $this->lobbyRepository->addPlayer($lobby->id, $guest->id, $name, false);

        $fresh = $this->lobbyRepository->find($lobbyId);
        $this->broadcastLobbyUpdated($fresh);

        return $fresh;
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
        ?MatchFormat $matchFormatOverride = null,
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

        $matchFormat = $matchFormatOverride ?? MatchFormat::fromRecord($lobby);

        $mode = $scoringMode ?? $lobby->scoring_mode ?? 'each_own';

        if ($this->lobbyHasTempGuests($lobby) && $mode === 'each_own') {
            throw new \RuntimeException('Gracze tymczasowi wymagają trybu „na jednym urządzeniu”');
        }

        $hostPlayerId = $lobby->host->player?->id;
        foreach ($lobby->players as $lp) {
            if (! $lp->is_registered) {
                continue;
            }
            if ($lp->player_id === $hostPlayerId) {
                continue;
            }
            if (! $lp->is_ready) {
                throw new \RuntimeException('Wszyscy zarejestrowani gracze muszą potwierdzić gotowość');
            }
        }

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

        $lobby = $this->lobbyRepository->startGame($lobbyId, $matchFormat, $mode);

        $ffaSessionId = $this->ffaScoringService->createSessionForLobby(
            $lobby,
            $matchFormat,
            $mode,
            $finalOrderIds,
        );
        $this->lobbyRepository->attachFfaMeta($lobbyId, $ffaSessionId, $finalOrderIds);

        $lobby = $this->lobbyRepository->find($lobbyId);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    public function updateSettings(int $lobbyId, int $hostUserId, ?MatchFormat $matchFormat = null): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->updateSettings($lobbyId, $hostUserId, $matchFormat);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    public function updateScoringMode(int $lobbyId, int $hostUserId, string $scoringMode): QuickGameLobby
    {
        $lobby = $this->lobbyRepository->find($lobbyId);

        if ($scoringMode === 'each_own' && $this->lobbyHasTempGuests($lobby)) {
            throw new \RuntimeException('Gracze tymczasowi wymagają trybu „na jednym urządzeniu”');
        }

        $lobby = $this->lobbyRepository->updateScoringMode($lobbyId, $hostUserId, $scoringMode);
        $this->broadcastLobbyUpdated($lobby);

        return $lobby;
    }

    /**
     * Zgłoszenie chęci rematchu przez uczestnika zakończonego lobby.
     * Jeśli host już utworzył rematch — dołącza gracza bez zaproszenia.
     *
     * @return array<string, mixed>
     */
    public function expressRematchIntent(int $sourceLobbyId, int $userId): array
    {
        $source = $this->lobbyRepository->find($sourceLobbyId);
        $this->assertLobbyFinished($source);
        $player = $this->assertRegisteredParticipant($source, $userId);

        if ($source->rematch_lobby_id) {
            $this->lobbyRepository->upsertRematchIntent($source->id, $player->id);
            $rematch = $this->lobbyRepository->find((int) $source->rematch_lobby_id);
            $this->ensurePlayerInRematchLobby($rematch, $player);
            $rematch = $this->lobbyRepository->find((int) $source->rematch_lobby_id);

            return [
                'status' => 'created',
                'sourceLobbyId' => $source->id,
                'waitingForHost' => false,
                'intents' => $this->formatRematchIntents($source->id),
                'lobby' => QuickGameLobbyPayload::fromLobby($rematch, $userId),
            ];
        }

        $this->lobbyRepository->upsertRematchIntent($source->id, $player->id);
        $intents = $this->formatRematchIntents($source->id);
        broadcast(new QuickGameRematchIntentUpdated(
            $source->id,
            (int) $source->host_id,
            $intents,
        ));

        return [
            'status' => 'waiting_for_host',
            'sourceLobbyId' => $source->id,
            'waitingForHost' => true,
            'intents' => $intents,
            'lobby' => null,
        ];
    }

    /**
     * Host tworzy nowe lobby rematch z ustawieniami źródła i auto-dodaje
     * graczy z intentami (+ gości z poprzedniego meczu).
     *
     * @return array<string, mixed>
     */
    public function createRematch(int $sourceLobbyId, int $hostUserId): array
    {
        $source = $this->lobbyRepository->find($sourceLobbyId);
        $this->assertLobbyFinished($source);

        if ((int) $source->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może utworzyć rematch');
        }

        if ($source->rematch_lobby_id) {
            $existing = $this->lobbyRepository->find((int) $source->rematch_lobby_id);

            return [
                'status' => 'created',
                'sourceLobbyId' => $source->id,
                'waitingForHost' => false,
                'intents' => $this->formatRematchIntents($source->id),
                'lobby' => QuickGameLobbyPayload::fromLobby($existing, $hostUserId),
            ];
        }

        $hostPlayer = $this->assertRegisteredParticipant($source, $hostUserId);
        $this->lobbyRepository->upsertRematchIntent($source->id, $hostPlayer->id);

        $rematch = DB::transaction(function () use ($source, $hostUserId) {
            $rematch = $this->lobbyRepository->create($hostUserId);
            $hostPlayer = $this->playerRepository->findByUserId($hostUserId);
            if ($hostPlayer) {
                $this->lobbyRepository->addPlayer($rematch->id, $hostPlayer->id, null, true);
            }

            $matchFormat = MatchFormat::fromRecord($source);
            $this->lobbyRepository->updateSettings($rematch->id, $hostUserId, $matchFormat);

            $scoringMode = $source->scoring_mode ?? 'each_own';
            $this->lobbyRepository->updateScoringMode($rematch->id, $hostUserId, $scoringMode);

            $rematch = $this->lobbyRepository->find($rematch->id);
            $intentPlayerIds = $this->lobbyRepository->getRematchIntentPlayerIds($source->id);
            $hostPlayerId = $hostPlayer?->id;

            foreach ($intentPlayerIds as $playerId) {
                if ($hostPlayerId !== null && $playerId === $hostPlayerId) {
                    continue;
                }
                if ($rematch->players->contains('player_id', $playerId)) {
                    continue;
                }
                $this->assertLobbyHasRoom($rematch);
                $this->lobbyRepository->addPlayerReady($rematch->id, $playerId, null, true, true);
                $rematch = $this->lobbyRepository->find($rematch->id);
            }

            foreach ($source->players as $lp) {
                if ($lp->is_registered) {
                    continue;
                }
                $name = trim((string) ($lp->temp_player_name ?? $lp->player?->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $this->assertLobbyHasRoom($rematch);
                try {
                    $this->assertGuestNameAvailableInLobby($rematch, $name);
                } catch (\RuntimeException) {
                    continue;
                }
                $guest = $this->playerRepository->createQuickGameGuest($name);
                $this->lobbyRepository->addPlayer($rematch->id, $guest->id, $name, false);
                $rematch = $this->lobbyRepository->find($rematch->id);
            }

            $this->lobbyRepository->setRematchLobbyId($source->id, $rematch->id);

            return $this->lobbyRepository->find($rematch->id);
        });

        $this->broadcastLobbyUpdated($rematch);
        broadcast(new QuickGameRematchCreated($source->id, $rematch));

        return [
            'status' => 'created',
            'sourceLobbyId' => $source->id,
            'waitingForHost' => false,
            'intents' => $this->formatRematchIntents($source->id),
            'lobby' => QuickGameLobbyPayload::fromLobby($rematch, $hostUserId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRematchStatus(int $sourceLobbyId, int $userId): array
    {
        $source = $this->lobbyRepository->find($sourceLobbyId);
        $this->assertLobbyFinished($source);
        $this->assertRegisteredParticipant($source, $userId);

        if ($source->rematch_lobby_id) {
            $rematch = $this->lobbyRepository->find((int) $source->rematch_lobby_id);

            return [
                'status' => 'created',
                'sourceLobbyId' => $source->id,
                'waitingForHost' => false,
                'intents' => $this->formatRematchIntents($source->id),
                'lobby' => QuickGameLobbyPayload::fromLobby($rematch, $userId),
            ];
        }

        return [
            'status' => 'waiting_for_host',
            'sourceLobbyId' => $source->id,
            'waitingForHost' => true,
            'intents' => $this->formatRematchIntents($source->id),
            'lobby' => null,
        ];
    }

    private function assertLobbyFinished(QuickGameLobby $lobby): void
    {
        if ($lobby->status !== 'finished') {
            throw new \RuntimeException('Rematch jest dostępny tylko po zakończeniu meczu');
        }
    }

    private function assertRegisteredParticipant(QuickGameLobby $lobby, int $userId): PlayerDomain
    {
        $player = $this->playerRepository->findByUserId($userId);
        if (! $player) {
            throw new \RuntimeException('Nie znaleziono gracza dla użytkownika');
        }

        if ((int) $lobby->host_id === $userId) {
            return $player;
        }

        $inLobby = $lobby->players->contains(
            fn ($lp) => $lp->is_registered && (int) $lp->player_id === (int) $player->id
        );
        if (! $inLobby) {
            throw new \RuntimeException('Nie byłeś uczestnikiem tego meczu');
        }

        return $player;
    }

    private function ensurePlayerInRematchLobby(QuickGameLobby $rematch, PlayerDomain $player): void
    {
        if ($rematch->status !== 'waiting') {
            return;
        }

        if ($rematch->players->contains('player_id', $player->id)) {
            return;
        }

        if ((int) $rematch->host_id === (int) ($player->userId ?? 0)) {
            return;
        }

        $this->assertLobbyHasRoom($rematch);
        $this->lobbyRepository->addPlayerReady($rematch->id, $player->id, null, true, true);
        $fresh = $this->lobbyRepository->find($rematch->id);
        $this->broadcastLobbyUpdated($fresh);
    }

    /**
     * @return list<array{playerId: int, name: string}>
     */
    private function formatRematchIntents(int $sourceLobbyId): array
    {
        return $this->lobbyRepository->getRematchIntents($sourceLobbyId)
            ->map(fn ($intent) => [
                'playerId' => (int) $intent->player_id,
                'name' => $intent->player?->name ?? 'Gracz',
            ])
            ->values()
            ->all();
    }

    private function broadcastLobbyUpdatedById(int $lobbyId): void
    {
        $this->broadcastLobbyUpdated($this->lobbyRepository->find($lobbyId));
    }

    private function broadcastLobbyUpdated(QuickGameLobby $lobby): void
    {
        broadcast(new QuickGameLobbyUpdated($lobby));
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

    private function lobbyHasTempGuests(QuickGameLobby $lobby): bool
    {
        return $lobby->players->contains(fn ($lp) => ! $lp->is_registered);
    }

    private function assertGuestNameAvailableInLobby(QuickGameLobby $lobby, string $name): void
    {
        $normalized = mb_strtolower($name);

        foreach ($lobby->players as $lp) {
            $displayName = $lp->player?->name ?? $lp->temp_player_name ?? '';
            if (mb_strtolower(trim($displayName)) === $normalized) {
                throw new \RuntimeException('Gracz o tej nazwie jest już w lobby');
            }
        }
    }
}
