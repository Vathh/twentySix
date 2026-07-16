<?php

namespace App\Services\QuickGame;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameFfaPresence;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Support\GameScoring\MatchFormat;
use DomainException;
use Illuminate\Support\Facades\DB;

class QuickGameFfaPresenceService
{
    public const HEARTBEAT_TIMEOUT_SECONDS = 90;

    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaPresenceRepository $presenceRepository,
        private QuickGameFfaScoringService $scoringService,
        private PlayerRepository $playerRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePresence(int $lobbyId, int $userId, string $status): array
    {
        if (! in_array($status, [
            QuickGameFfaPresence::STATUS_CONNECTED,
            QuickGameFfaPresence::STATUS_DISCONNECTED,
            QuickGameFfaPresence::STATUS_LEFT,
        ], true)) {
            throw new DomainException('Nieprawidłowy status obecności.');
        }

        return DB::transaction(function () use ($lobbyId, $userId, $status) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $player = $this->playerRepository->findByUserId($userId);
            if ($player === null) {
                throw new DomainException('Nie znaleziono gracza.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            if (! in_array((int) $player->id, $playerIds, true)) {
                throw new DomainException('Nie jesteś uczestnikiem tego meczu.');
            }

            $presence = $this->presenceRepository->findForSessionPlayer($session, (int) $player->id);
            if ($presence === null) {
                $this->presenceRepository->initializeForSession($session, $playerIds);
                $presence = $this->presenceRepository->findForSessionPlayer($session, (int) $player->id);
            }

            if ($presence->status === QuickGameFfaPresence::STATUS_LEFT) {
                if ($status === QuickGameFfaPresence::STATUS_LEFT) {
                    return $this->scoringService->getState($lobbyId, $userId);
                }
                throw new DomainException('Opuszczono ten mecz — nie można wrócić.');
            }

            if ($status === QuickGameFfaPresence::STATUS_LEFT) {
                $presence->status = QuickGameFfaPresence::STATUS_LEFT;
                $presence->left_at = now();
                $this->presenceRepository->save($presence);

                if ($this->shouldForfeitOnLeave($session)) {
                    $winnerId = $this->resolveForfeitWinnerId($session, (int) $player->id);

                    return $this->scoringService->forfeitToPlayer($lobbyId, $winnerId, $userId);
                }

                return $this->scoringService->handlePlayerLeft(
                    $lobbyId,
                    (int) $player->id,
                    $userId,
                );
            }

            $presence->status = $status;
            if ($status === QuickGameFfaPresence::STATUS_CONNECTED) {
                $presence->last_seen_at = now();
            }
            $this->presenceRepository->save($presence);

            return $this->scoringService->broadcastState($lobbyId, $userId);
        });
    }

    public function syncStaleDisconnects(QuickGameFfaSession $session): void
    {
        if (! $session->isInProgress()) {
            return;
        }

        // Na jednym urządzeniu host wpisuje wszystkich — heartbeat innych nie ma znaczenia.
        if ($session->scoring_mode === 'one_device') {
            return;
        }

        $playerIds = array_map('intval', $session->player_order ?? []);
        $this->presenceRepository->markStaleAsDisconnected(
            $session,
            $this->heartbeatTrackedPlayerIds($playerIds),
            self::HEARTBEAT_TIMEOUT_SECONDS,
        );
    }

    /**
     * @return array<int, array{playerId: int, name: string, status: string}>
     */
    public function buildPresencePayload(QuickGameFfaSession $session): array
    {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $records = $this->presenceRepository->getForSession($session)->keyBy('player_id');
        $payload = [];

        foreach ($playerIds as $playerId) {
            $record = $records->get($playerId);
            $player = $record?->player;
            $isGuestWithoutAccount = $player !== null && $player->user_id === null;
            $status = $record?->status ?? QuickGameFfaPresence::STATUS_CONNECTED;
            // Goście lokalni nie łączą się z apką — zawsze traktuj jako connected.
            if ($isGuestWithoutAccount && $status === QuickGameFfaPresence::STATUS_DISCONNECTED) {
                $status = QuickGameFfaPresence::STATUS_CONNECTED;
            }
            $payload[] = [
                'playerId' => $playerId,
                'name' => $player?->name ?? 'Gracz',
                'status' => $status,
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveMatchForUser(int $userId): ?array
    {
        $player = $this->playerRepository->findByUserId($userId);
        if ($player === null) {
            return null;
        }

        $sessions = QuickGameFfaSession::query()
            ->where('status', QuickGameFfaSession::STATUS_IN_PROGRESS)
            ->whereJsonContains('player_order', (int) $player->id)
            ->whereHas('lobby', fn ($q) => $q->where('status', 'started'))
            ->with(['lobby.players.player'])
            ->get();

        foreach ($sessions as $session) {
            $session->refresh();

            if (! $session->isInProgress()) {
                continue;
            }

            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);
            if (in_array((int) $player->id, $leftIds, true)) {
                continue;
            }

            $lobby = $session->lobby;
            if ($lobby === null || $lobby->status !== 'started') {
                continue;
            }

            if ((int) $lobby->ffa_session_id !== (int) $session->id) {
                continue;
            }

            return $this->buildActiveMatchPayload($lobby, $session, $userId, (int) $player->id);
        }

        return null;
    }

    public function isFfaParticipant(QuickGameLobby $lobby, int $userId): bool
    {
        if ((int) $lobby->host_id === $userId) {
            return true;
        }

        if ($lobby->status === 'started' && $lobby->ffa_session_id) {
            $session = $lobby->ffaSession;
            if ($session === null) {
                $session = $this->sessionRepository->findForLobby($lobby->id);
            }

            if ($session !== null) {
                $player = $this->playerRepository->findByUserId($userId);
                if ($player !== null) {
                    $playerIds = array_map('intval', $session->player_order ?? []);
                    if (in_array((int) $player->id, $playerIds, true)) {
                        $presence = $this->presenceRepository->findForSessionPlayer($session, (int) $player->id);
                        if ($presence?->status !== QuickGameFfaPresence::STATUS_LEFT) {
                            return true;
                        }
                    }
                }
            }
        }

        foreach ($lobby->players as $lp) {
            if ($lp->player_id && $lp->player && (int) $lp->player->user_id === $userId) {
                return true;
            }
        }

        return false;
    }

    private function shouldForfeitOnLeave(QuickGameFfaSession $session): bool
    {
        $playerIds = $session->player_order ?? [];

        return $session->scoring_mode === 'each_own'
            && count($playerIds) === 2
            && $session->isInProgress();
    }

    /**
     * ID graczy śledzonych heartbeatem (bez gości lokalnych bez konta).
     *
     * @param  array<int, int>  $playerIds
     * @return array<int, int>
     */
    private function heartbeatTrackedPlayerIds(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        $guestIds = Player::query()
            ->whereIn('id', $playerIds)
            ->whereNull('user_id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($playerIds, $guestIds));
    }

    private function resolveForfeitWinnerId(QuickGameFfaSession $session, int $leavingPlayerId): int
    {
        $playerIds = array_map('intval', $session->player_order ?? []);
        foreach ($playerIds as $playerId) {
            if ($playerId !== $leavingPlayerId) {
                return $playerId;
            }
        }

        throw new DomainException('Nie można ustalić zwycięzcy walkoweru.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActiveMatchPayload(
        QuickGameLobby $lobby,
        QuickGameFfaSession $session,
        int $userId,
        int $myPlayerId,
    ): array {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');
        $lobbyPlayers = $lobby->players->keyBy('player_id');

        $playersPayload = [];
        foreach ($playerIds as $index => $playerId) {
            $p = $players->get($playerId);
            $lp = $lobbyPlayers->get($playerId);
            $playersPayload[] = [
                'id' => $playerId,
                'name' => $p?->name ?? 'Gracz',
                'orderIndex' => $index,
                'isGuest' => $lp ? ! $lp->player_id : false,
            ];
        }

        $myPlayerIndex = array_search($myPlayerId, $playerIds, true);

        return [
            'lobbyId' => $lobby->id,
            'matchFormat' => MatchFormat::fromRecord($session)->toArray(),
            'gameType' => $session->game_type,
            'scoringMode' => $session->scoring_mode,
            'isHost' => (int) $lobby->host_id === $userId,
            'myPlayerIndex' => $myPlayerIndex === false ? null : (int) $myPlayerIndex,
            'players' => $playersPayload,
        ];
    }
}
