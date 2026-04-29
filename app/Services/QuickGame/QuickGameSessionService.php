<?php

namespace App\Services\QuickGame;

use App\Repositories\QuickGame\QuickGameSessionRepository;

class QuickGameSessionService
{
    public function __construct(
        private QuickGameSessionRepository $sessionRepository
    ) {
    }

    /**
     * Tworzy sesję z początkowym stanem (501).
     */
    public function createSession(
        int $lobbyId,
        int $hostUserId,
        string $scoringMode,
        string $gameType,
        int $legsCount,
        int $playersCount,
        array $playerOrderLobbyPlayerIds = []
    ): \App\Models\QuickGameSession
    {
        $initialScores = array_fill(0, $playersCount, 501);
        $state = [
            'gameType' => $gameType,
            'legsCount' => $legsCount,
            'legsWon' => array_fill(0, $playersCount, 0),
            'playerScores' => $initialScores,
            'currentPlayerIndex' => 0,
            'legStartingPlayerIndex' => 0,
            'status' => 'in_progress',
            'playerOrderLobbyPlayerIds' => array_values($playerOrderLobbyPlayerIds),
        ];
        return $this->sessionRepository->create($lobbyId, $hostUserId, $scoringMode, $state);
    }

    /**
     * Pobiera stan sesji i indeks bieżącego użytkownika w liście graczy.
     * @return array{state: array, players: array, scoringMode: string, hostUserId: int, myPlayerIndex: int|null}
     */
    public function getState(int $sessionId, ?int $currentUserId): array
    {
        $session = $this->sessionRepository->find($sessionId);
        $session->load('lobby.players.player');
        $orderedLobbyPlayers = $session->lobby->players;
        $orderIds = $session->state['playerOrderLobbyPlayerIds'] ?? [];
        if (is_array($orderIds) && count($orderIds) > 0) {
            $orderPos = array_flip(array_map('intval', $orderIds));
            $orderedLobbyPlayers = $orderedLobbyPlayers->sortBy(function ($p) use ($orderPos) {
                $id = (int) $p->id;
                return $orderPos[$id] ?? (10000 + $id);
            })->values();
        }

        $players = $orderedLobbyPlayers->map(function ($p) {
            return [
                'id' => $p->id,
                'playerId' => $p->player_id,
                'name' => $p->player?->name ?? $p->temp_player_name ?? 'Gracz',
            ];
        })->values()->all();

        $myPlayerIndex = null;
        if ($currentUserId !== null) {
            foreach ($orderedLobbyPlayers as $i => $lp) {
                if ($lp->player_id && $lp->player && (int) $lp->player->user_id === (int) $currentUserId) {
                    $myPlayerIndex = $i;
                    break;
                }
            }
        }

        return [
            'state' => $session->state,
            'players' => $players,
            'scoringMode' => $session->scoring_mode,
            'hostUserId' => $session->host_user_id,
            'myPlayerIndex' => $myPlayerIndex,
        ];
    }

    /**
     * Zapisuje wizytę (rzut) i aktualizuje stan. Dla 501.
     * @param int $sessionId
     * @param int $currentUserId
     * @param int $playerIndex indeks gracza, który rzucał
     * @param int $visitScore suma punktów z wizyty
     * @param bool $bust czy bust (przekroczenie)
     */
    public function submitVisit(int $sessionId, int $currentUserId, int $playerIndex, int $visitScore, bool $bust = false): array
    {
        $session = $this->sessionRepository->find($sessionId);
        $state = $session->state;
        $n = count($state['playerScores']);

        if ($state['status'] !== 'in_progress') {
            throw new \RuntimeException('Mecz jest zakończony');
        }
        if ($playerIndex < 0 || $playerIndex >= $n) {
            throw new \RuntimeException('Nieprawidłowy indeks gracza');
        }
        if ($playerIndex !== $state['currentPlayerIndex']) {
            throw new \RuntimeException('Teraz rzuca inny gracz');
        }

        $isHost = (int) $session->host_user_id === (int) $currentUserId;
        if ($session->scoring_mode === 'one_device' && !$isHost) {
            throw new \RuntimeException('W trybie „liczenie na 1 urządzeniu” tylko host może wpisywać punkty');
        }

        if ($state['gameType'] !== '501') {
            throw new \RuntimeException('Obsługiwany jest tylko typ gry 501');
        }

        if ($bust) {
            $state['currentPlayerIndex'] = ($state['currentPlayerIndex'] + 1) % $n;
            $this->sessionRepository->updateState($sessionId, $state);
            return $this->getState($sessionId, $currentUserId);
        }

        $currentScore = $state['playerScores'][$playerIndex];
        $newScore = $currentScore - $visitScore;
        if ($newScore < 0) {
            $state['currentPlayerIndex'] = ($state['currentPlayerIndex'] + 1) % $n;
            $this->sessionRepository->updateState($sessionId, $state);
            return $this->getState($sessionId, $currentUserId);
        }

        if ($newScore === 0) {
            $state['legsWon'][$playerIndex]++;
            if ($state['legsWon'][$playerIndex] >= $state['legsCount']) {
                $state['status'] = 'finished';
                $this->sessionRepository->updateState($sessionId, $state);
                return $this->getState($sessionId, $currentUserId);
            }
            $state['legStartingPlayerIndex'] = ($state['currentPlayerIndex'] + 1) % $n;
            $state['currentPlayerIndex'] = $state['legStartingPlayerIndex'];
            $state['playerScores'] = array_fill(0, $n, 501);
            $this->sessionRepository->updateState($sessionId, $state);
            return $this->getState($sessionId, $currentUserId);
        }

        $state['playerScores'][$playerIndex] = $newScore;
        $state['currentPlayerIndex'] = ($state['currentPlayerIndex'] + 1) % $n;
        $this->sessionRepository->updateState($sessionId, $state);
        return $this->getState($sessionId, $currentUserId);
    }
}











