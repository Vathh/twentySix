<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\PlayerResultDTO;
use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Events\QuickGameFfaStateUpdated;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameFfaVisitRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Support\QuickGameFfa\QuickGameFfaStateBuilder;
use App\Support\QuickGameLobbyPlayerOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

class QuickGameFfaScoringService
{
    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaVisitRepository $visitRepository,
        private QuickGameFfaStateBuilder $stateBuilder,
        private PlayerRepository $playerRepository,
        private QuickGameRepository $quickGameRepository,
    ) {
    }

    /**
     * @param  array<int, int>  $lobbyPlayerOrderIds  lobby_player.id w kolejności
     */
    public function createSessionForLobby(
        QuickGameLobby $lobby,
        int $legsToWin,
        string $gameType,
        string $scoringMode,
        array $lobbyPlayerOrderIds,
    ): int {
        $lobby->loadMissing('players.player');
        $ordered = QuickGameLobbyPlayerOrder::sort($lobby->players, $lobbyPlayerOrderIds);

        $playerIds = [];
        foreach ($ordered as $lp) {
            if ($lp->player_id === null) {
                throw new DomainException('Wszyscy uczestnicy quick game muszą być zarejestrowani.');
            }
            $playerIds[] = (int) $lp->player_id;
        }

        $count = count($playerIds);
        if ($count < 2 || $count > QuickGameLobbyService::MAX_LOBBY_PLAYERS) {
            throw new DomainException('Quick game FFA wymaga od 2 do '.QuickGameLobbyService::MAX_LOBBY_PLAYERS.' graczy.');
        }

        if ($this->sessionRepository->findForLobby($lobby->id) !== null) {
            throw new DomainException('Sesja FFA dla tego lobby już istnieje.');
        }

        $session = $this->sessionRepository->create([
            'lobby_id' => $lobby->id,
            'legs_to_win' => max(1, min(15, $legsToWin)),
            'game_type' => $gameType,
            'scoring_mode' => $scoringMode,
            'starting_score' => 501,
            'status' => \App\Models\QuickGame\QuickGameFfaSession::STATUS_IN_PROGRESS,
            'player_order' => $playerIds,
            'leg_opener_index' => 0,
            'current_player_index' => 0,
            'current_leg_number' => 1,
            'state_version' => 1,
            'started_at' => now(),
        ]);

        return $session->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(int $lobbyId, ?int $userId = null): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->loadMissing('lobby');
        $visits = $this->visitRepository->getActiveForSession($session);

        return $this->stateBuilder->build($session, $visits, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVisit(int $lobbyId, int $userId, RecordFfaVisitDTO $dto): array
    {
        return DB::transaction(function () use ($lobbyId, $userId, $dto) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $existing = $this->visitRepository->findByClientVisitId($dto->clientVisitId);
            if ($existing !== null) {
                return $this->broadcastState($session, $userId);
            }

            $playerIds = $session->player_order ?? [];
            $n = count($playerIds);
            if ($n < 2) {
                throw new DomainException('Nieprawidłowa sesja FFA.');
            }

            if (! in_array($dto->playerId, $playerIds, true)) {
                throw new DomainException('Gracz nie należy do tego meczu.');
            }

            $currentPlayerId = (int) $playerIds[$session->current_player_index];
            if ($dto->playerId !== $currentPlayerId) {
                throw new DomainException('Teraz rzuca inny gracz.');
            }

            $this->assertCanSubmitVisit($session, $userId, $dto->playerId);

            $this->visitRepository->create($session, (int) $session->current_leg_number, $dto);
            $this->applyTurnAfterVisit($session, $dto, $n);

            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            if ($session->fresh()->status === \App\Models\QuickGame\QuickGameFfaSession::STATUS_FINISHED) {
                // finished in applyTurnAfterVisit via finishMatch
            }

            return $this->broadcastState($session->fresh(), $userId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function undoLastVisit(int $lobbyId, int $userId): array
    {
        return DB::transaction(function () use ($lobbyId, $userId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $this->assertCanSubmitVisit($session, $userId, null);

            $voided = $this->visitRepository->voidLastForLeg($session, (int) $session->current_leg_number);
            if ($voided === null) {
                throw new DomainException('Brak wizyty do cofnięcia.');
            }

            $this->recomputeIndicesFromVisits($session);
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastState($session->fresh(), $userId);
        });
    }

    private function assertCanSubmitVisit(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        int $userId,
        ?int $visitPlayerId,
    ): void {
        $lobby = $session->lobby;
        if ($lobby === null) {
            throw new DomainException('Lobby nie istnieje.');
        }

        $playerIds = array_map('intval', $session->player_order ?? []);

        if ($session->scoring_mode === 'one_device') {
            if ((int) $lobby->host_id !== $userId) {
                throw new DomainException('W trybie jednego urządzenia punkty wpisuje tylko host.');
            }

            return;
        }

        $player = $this->playerRepository->findByUserId($userId);
        if ($player === null) {
            throw new DomainException('Nie znaleziono gracza.');
        }

        if ($visitPlayerId === null) {
            if (! in_array((int) $player->id, $playerIds, true)) {
                throw new DomainException('Nie jesteś uczestnikiem tego meczu.');
            }

            return;
        }

        if ((int) $player->id !== $visitPlayerId) {
            throw new DomainException('Możesz wpisywać tylko własne rzuty.');
        }
    }

    private function applyTurnAfterVisit(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        RecordFfaVisitDTO $dto,
        int $n,
    ): void {
        if ($dto->bust) {
            return;
        }

        if ($dto->closedLeg) {
            $this->advanceAfterLegClosed($session, $dto->playerId, $n);

            return;
        }

        $session->current_player_index = ((int) $session->current_player_index + 1) % $n;
    }

    private function advanceAfterLegClosed(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        int $winnerPlayerId,
        int $n,
    ): void {
        $visits = $this->visitRepository->getActiveForSession($session);
        $playerIds = $session->player_order ?? [];
        $legsWon = $this->countLegsWonFromVisits($visits, $playerIds);

        if (($legsWon[$winnerPlayerId] ?? 0) >= (int) $session->legs_to_win) {
            $this->finishMatch($session, $legsWon);

            return;
        }

        $session->leg_opener_index = ((int) $session->leg_opener_index + 1) % $n;
        $session->current_player_index = (int) $session->leg_opener_index;
        $session->current_leg_number = (int) $session->current_leg_number + 1;
    }

    /**
     * @param  array<int, int>  $playerIds
     * @return array<int, int>
     */
    private function countLegsWonFromVisits($visits, array $playerIds): array
    {
        $legsWon = array_fill_keys($playerIds, 0);
        foreach ($visits->groupBy('leg_number') as $legVisits) {
            $winner = $legVisits
                ->filter(fn ($v) => $v->closed_leg && ! $v->bust && (int) $v->remaining_after === 0)
                ->sortByDesc('visit_number')
                ->first();
            if ($winner && isset($legsWon[$winner->player_id])) {
                $legsWon[$winner->player_id]++;
            }
        }

        return $legsWon;
    }

    /**
     * @param  array<int, int>  $legsWon
     */
    private function finishMatch(\App\Models\QuickGame\QuickGameFfaSession $session, array $legsWon): void
    {
        $playerIds = $session->player_order ?? [];
        $visits = $this->visitRepository->getActiveForSession($session);

        $ranked = collect($playerIds)
            ->map(fn ($pid) => ['playerId' => (int) $pid, 'score' => (int) ($legsWon[$pid] ?? 0)])
            ->sortByDesc('score')
            ->values();

        $results = [];
        foreach ($ranked as $i => $row) {
            $pid = $row['playerId'];
            $legVisits = $visits->where('player_id', $pid);
            $darts = $legVisits->sum('darts_in_visit') ?: null;
            $totalScore = $legVisits->where('bust', false)->sum('score');
            $avg = $darts > 0 ? round(($totalScore / $darts) * 3, 2) : null;

            $results[] = new PlayerResultDTO(
                playerId: $pid,
                score: $row['score'],
                place: $i + 1,
                average: $avg,
                dartsThrown: $darts ? (int) $darts : null,
                pointsEarned: $totalScore ? (int) $totalScore : null,
            );
        }

        $quickGameId = $this->quickGameRepository->createWithResults($playerIds, $session->lobby_id);
        $this->quickGameRepository->saveResults($quickGameId, $results);

        $winnerId = $ranked->first()['playerId'] ?? null;
        $p1 = $playerIds[0] ?? null;
        $p2 = $playerIds[1] ?? null;

        \App\Models\QuickGame\QuickGame::where('id', $quickGameId)->update([
            'player1_score' => (int) ($legsWon[$p1] ?? 0),
            'player2_score' => (int) ($legsWon[$p2] ?? 0),
            'winner_id' => $winnerId,
            'status' => \App\Enums\GameStatus::FINISHED,
            'legs_count' => $session->legs_to_win,
        ]);

        $session->status = \App\Models\QuickGame\QuickGameFfaSession::STATUS_FINISHED;
        $session->quick_game_id = $quickGameId;
        $session->finished_at = now();
    }

    private function recomputeIndicesFromVisits(\App\Models\QuickGame\QuickGameFfaSession $session): void
    {
        $playerIds = $session->player_order ?? [];
        $n = count($playerIds);
        $legNumber = (int) $session->current_leg_number;
        $visits = $this->visitRepository->getActiveForLeg($session, $legNumber);

        if ($visits->isEmpty()) {
            $session->current_player_index = (int) $session->leg_opener_index;

            return;
        }

        $last = $visits->sortByDesc('visit_number')->sortByDesc('id')->first();
        $lastIdx = array_search((int) $last->player_id, array_map('intval', $playerIds), true);

        if ($lastIdx === false) {
            return;
        }

        if ($last->bust) {
            $session->current_player_index = (int) $lastIdx;
        } else {
            $session->current_player_index = ((int) $lastIdx + 1) % $n;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcastState(\App\Models\QuickGame\QuickGameFfaSession $session, ?int $userId): array
    {
        $session->loadMissing('lobby');
        $visits = $this->visitRepository->getActiveForSession($session);
        $state = $this->stateBuilder->build($session, $visits, $userId);
        broadcast(new QuickGameFfaStateUpdated($session->lobby_id, $state));

        return $state;
    }
}
