<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\PlayerResultDTO;
use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Domain\QuickGame\FfaSessionRulesDomain;
use App\Domain\QuickGame\FfaTurnRotationDomain;
use App\Events\QuickGameFfaStateUpdated;
use App\Models\QuickGame\QuickGameFfaPresence;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameFfaVisitRepository;
use App\Repositories\QuickGame\QuickGameLobbyRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Support\QuickGameFfa\QuickGameFfaStateBuilder;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\QuickGame\Bob27Rules;
use App\Support\QuickGameFfa\CricketRules;
use App\Domain\GameScoring\MatchFormatScoring;
use App\Domain\GameScoring\VisitRecorder;
use App\Support\QuickGameLobbyPlayerOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

class QuickGameFfaScoringService
{
    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaVisitRepository $visitRepository,
        private QuickGameFfaPresenceRepository $presenceRepository,
        private QuickGameFfaStateBuilder $stateBuilder,
        private PlayerRepository $playerRepository,
        private QuickGameRepository $quickGameRepository,
        private QuickGameLobbyRepository $lobbyRepository,
        private QuickGameFfaCricketScoringService $cricketScoringService,
        private QuickGameFfaBob27ScoringService $bob27ScoringService,
    ) {
    }

    /**
     * @param  array<int, int>  $lobbyPlayerOrderIds  lobby_player.id w kolejności
     */
    public function createSessionForLobby(
        QuickGameLobby $lobby,
        MatchFormat $matchFormat,
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

        $matchFormat->validate();
        $emptyScores = array_fill_keys($playerIds, 0);

        $isCricket = $matchFormat->isCricket();
        $isBob27 = $matchFormat->isBob27();
        $setsToWin = ($isCricket || $isBob27) ? 1 : $matchFormat->setsToWinMatch;
        $gameType = $isCricket
            ? MatchFormat::GAME_TYPE_CRICKET
            : ($isBob27 ? MatchFormat::GAME_TYPE_BOB27 : $matchFormat->gameType);

        $session = $this->sessionRepository->create([
            'lobby_id' => $lobby->id,
            'legs_to_win_set' => $matchFormat->legsToWinSet,
            'sets_to_win_match' => $setsToWin,
            'game_type' => $gameType,
            'scoring_mode' => $scoringMode,
            'starting_score' => $matchFormat->startingScore,
            'status' => \App\Models\QuickGame\QuickGameFfaSession::STATUS_IN_PROGRESS,
            'player_order' => $playerIds,
            'legs_won_in_set' => $emptyScores,
            'sets_won' => $emptyScores,
            'cricket_state' => $isCricket
                ? CricketRules::initialState($playerIds)
                : null,
            'bob27_state' => $isBob27
                ? Bob27Rules::initialState($playerIds, $matchFormat->bob27Mode)
                : null,
            'leg_opener_index' => 0,
            'current_player_index' => 0,
            'current_leg_number' => 1,
            'current_set_number' => 1,
            'state_version' => 1,
            'started_at' => now(),
        ]);

        $this->presenceRepository->initializeForSession($session, $playerIds);

        return $session->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(int $lobbyId, ?int $userId = null): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->loadMissing('lobby');

        if (strtolower((string) $session->game_type) === MatchFormat::GAME_TYPE_CRICKET) {
            return $this->cricketScoringService->getState($lobbyId, $userId);
        }
        if (strtolower((string) $session->game_type) === MatchFormat::GAME_TYPE_BOB27) {
            return $this->bob27ScoringService->getState($lobbyId, $userId);
        }

        $this->syncStalePresence($session);
        $visits = $this->visitRepository->getActiveForSession($session);
        $presence = $this->buildPresencePayload($session);

        return $this->stateBuilder->build($session, $visits, $userId, $presence);
    }

    /**
     * Gracz świadomie opuścił mecz (3+ graczy) — pomijamy go w rotacji, mecz trwa dalej.
     *
     * @return array<string, mixed>
     */
    public function handlePlayerLeft(int $lobbyId, int $leavingPlayerId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($lobbyId, $leavingPlayerId, $userId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);

            if (! in_array($leavingPlayerId, $leftIds, true)) {
                throw new DomainException('Gracz nie został oznaczony jako opuścił mecz.');
            }

            $activeIds = FfaTurnRotationDomain::activePlayerIds($playerIds, $leftIds);

            if (FfaSessionRulesDomain::isDecidedByForfeit($activeIds)) {
                $winnerId = FfaSessionRulesDomain::soleRemainingPlayerId($activeIds);
                if ($winnerId === null) {
                    throw new DomainException('Brak aktywnych graczy w meczu.');
                }

                return $this->forfeitToPlayer($lobbyId, $winnerId, $userId);
            }

            $this->normalizeTurnIndicesForLeftPlayers($session, $playerIds, $leftIds);
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastStateForSession($session->fresh(), $userId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function forfeitToPlayer(int $lobbyId, int $winnerPlayerId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($lobbyId, $winnerPlayerId, $userId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            if (! in_array($winnerPlayerId, $playerIds, true)) {
                throw new DomainException('Nieprawidłowy zwycięzca walkoweru.');
            }

            $format = MatchFormat::fromRecord($session);
            $legsWonInSet = $session->legs_won_in_set ?? [];
            $setsWon = $session->sets_won ?? [];
            foreach ($playerIds as $pid) {
                $legsWonInSet[$pid] ??= 0;
                $setsWon[$pid] ??= 0;
            }
            $legsWonInSet[$winnerPlayerId] = max(
                (int) ($legsWonInSet[$winnerPlayerId] ?? 0),
                $format->legsToWinSet,
            );
            $setsWon[$winnerPlayerId] = $format->setsToWinMatch;

            $this->finishMatch($session, MatchFormatScoring::legsWonForDisplay($format, $legsWonInSet, $setsWon), $format);
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastStateForSession($session->fresh(), $userId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastState(int $lobbyId, ?int $userId = null): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->loadMissing('lobby');
        $this->syncStalePresence($session);

        return $this->broadcastStateForSession($session, $userId);
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

            $gameType = strtolower((string) $session->game_type);
            if ($gameType === MatchFormat::GAME_TYPE_CRICKET) {
                throw new DomainException('Sesja cricket — użyj endpointu /ffa/cricket/darts.');
            }
            if ($gameType === MatchFormat::GAME_TYPE_BOB27) {
                throw new DomainException('Sesja Bob\'s 27 — użyj endpointu /ffa/bob27/darts.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $n = count($playerIds);
            if ($n < 2) {
                throw new DomainException('Nieprawidłowa sesja FFA.');
            }

            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);

            if (! in_array($dto->playerId, $playerIds, true)) {
                throw new DomainException('Gracz nie należy do tego meczu.');
            }

            if (in_array($dto->playerId, $leftIds, true)) {
                throw new DomainException('Ten gracz opuścił mecz.');
            }

            $existing = $this->visitRepository->findByClientVisitId($dto->clientVisitId);
            if ($existing !== null) {
                if ($existing->is_voided) {
                    throw new DomainException('Ta wizyta została już cofnięta.');
                }
                if ((int) $existing->ffa_session_id !== (int) $session->id) {
                    throw new DomainException('Nieprawidłowa wizyta.');
                }

                $alreadyComplete = VisitRecorder::isVisitComplete(
                    (bool) $existing->bust,
                    (bool) $existing->closed_leg,
                    (int) $existing->darts_in_visit,
                );

                if ($alreadyComplete) {
                    // Idempotentny retry kompletnej wizyty — bez ponownego awansu tury
                    // i bez walidacji „czyja tura” (tablet mógł stracić odpowiedź).
                    return $this->broadcastStateForSession($session->fresh(), $userId);
                }
            }

            $this->normalizeTurnIndicesForLeftPlayers($session, $playerIds, $leftIds);

            $currentPlayerId = (int) $playerIds[$session->current_player_index];
            if ($dto->playerId !== $currentPlayerId) {
                throw new DomainException('Teraz rzuca inny gracz.');
            }

            $this->assertCanSubmitVisit($session, $userId, $dto->playerId);

            VisitRecorder::validateDto($dto, (int) $session->starting_score);

            if ($existing !== null) {
                $this->visitRepository->updateFromDto($existing, $dto);
                if (VisitRecorder::isVisitComplete($dto->bust, $dto->closedLeg, $dto->dartsInVisit)) {
                    $this->applyTurnAfterVisit($session, $dto, $playerIds, $leftIds);
                }
            } else {
                $this->visitRepository->create($session, (int) $session->current_leg_number, $dto);
                if (VisitRecorder::isVisitComplete($dto->bust, $dto->closedLeg, $dto->dartsInVisit)) {
                    $this->applyTurnAfterVisit($session, $dto, $playerIds, $leftIds);
                }
            }

            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            if ($session->fresh()->status === \App\Models\QuickGame\QuickGameFfaSession::STATUS_FINISHED) {
                // finished in applyTurnAfterVisit via finishMatch
            }

            return $this->broadcastStateForSession($session->fresh(), $userId);
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

            $legNumber = $this->resolveLegNumberForUndo($session);
            $voided = $this->visitRepository->voidLastForLeg($session, $legNumber);
            if ($voided === null) {
                throw new DomainException('Brak wizyty do cofnięcia.');
            }

            if ($voided->closed_leg) {
                $format = MatchFormat::fromRecord($session);
                $playerIds = array_map('intval', $session->player_order ?? []);
                $legsWonInSet = $session->legs_won_in_set ?? [];
                $setsWon = $session->sets_won ?? [];
                foreach ($playerIds as $pid) {
                    $legsWonInSet[$pid] ??= 0;
                    $setsWon[$pid] ??= 0;
                }

                $reverted = MatchFormatScoring::revertLegWinOnFfa(
                    $format,
                    (int) $voided->player_id,
                    $legsWonInSet,
                    $setsWon,
                    (int) ($session->current_set_number ?? 1),
                );
                $session->legs_won_in_set = $reverted['legsWonInSet'];
                $session->sets_won = $reverted['setsWon'];
                $session->current_set_number = $reverted['currentSetNumber'];

                if ((int) $session->current_leg_number > $legNumber) {
                    $session->current_leg_number = $legNumber;
                }
            }

            $this->recomputeIndicesFromVisits($session);
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastStateForSession($session->fresh(), $userId);
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
        $leftIds = $this->presenceRepository->getLeftPlayerIds($session);

        if ($visitPlayerId !== null && in_array($visitPlayerId, $leftIds, true)) {
            throw new DomainException('Ten gracz opuścił mecz.');
        }

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

        if (in_array((int) $player->id, $leftIds, true)) {
            throw new DomainException('Opuszczono ten mecz — nie możesz wpisywać rzutów.');
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
        array $playerIds,
        array $leftIds,
    ): void {
        if ($dto->closedLeg) {
            $this->advanceAfterLegClosed($session, $dto->playerId, $playerIds, $leftIds);

            return;
        }

        // Kompletna wizyta (w tym bust) — tura przechodzi dalej.
        $session->current_player_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->current_player_index,
            $playerIds,
            $leftIds,
        );
    }

    private function advanceAfterLegClosed(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        int $winnerPlayerId,
        array $playerIds,
        array $leftIds,
    ): void {
        $format = MatchFormat::fromRecord($session);
        $legsWonInSet = $session->legs_won_in_set ?? [];
        $setsWon = $session->sets_won ?? [];
        foreach ($playerIds as $pid) {
            $legsWonInSet[$pid] ??= 0;
            $setsWon[$pid] ??= 0;
        }

        $result = MatchFormatScoring::applyLegWinToFfa(
            $format,
            $winnerPlayerId,
            $legsWonInSet,
            $setsWon,
            (int) ($session->current_set_number ?? 1),
        );

        $session->legs_won_in_set = $result['legsWonInSet'];
        $session->sets_won = $result['setsWon'];
        $session->current_set_number = $result['currentSetNumber'];

        if ($result['finished']) {
            $this->finishMatch(
                $session,
                MatchFormatScoring::legsWonForDisplay($format, $result['legsWonInSet'], $result['setsWon']),
                $format,
            );

            return;
        }

        $session->leg_opener_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
        $session->current_player_index = (int) $session->leg_opener_index;
        $session->current_leg_number = (int) $session->current_leg_number + 1;
    }

    /**
     * @param  array<int, int>  $legsWon
     */
    private function finishMatch(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        array $legsWon,
        MatchFormat $format,
    ): void {
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

        $this->quickGameRepository->updateResultFields($quickGameId, array_merge(
            [
                'player1_score' => (int) ($legsWon[$p1] ?? 0),
                'player2_score' => (int) ($legsWon[$p2] ?? 0),
                'winner_id' => $winnerId,
                'status' => \App\Enums\GameStatus::FINISHED,
            ],
            $format->toDatabaseColumns(),
        ));

        $session->status = \App\Models\QuickGame\QuickGameFfaSession::STATUS_FINISHED;
        $session->quick_game_id = $quickGameId;
        $session->finished_at = now();

        $session->loadMissing('lobby');
        $lobby = $session->lobby;
        if ($lobby !== null) {
            $this->lobbyRepository->markFinished($lobby->id, $quickGameId);
        }
    }

    private function recomputeIndicesFromVisits(\App\Models\QuickGame\QuickGameFfaSession $session): void
    {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $legNumber = (int) $session->current_leg_number;
        $visits = $this->visitRepository->getActiveForLeg($session, $legNumber);
        $leftIds = $this->presenceRepository->getLeftPlayerIds($session);

        $computed = VisitRecorder::currentPlayerIndexFromVisits(
            $visits,
            $playerIds,
            (int) $session->leg_opener_index,
        );

        $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
            $computed,
            $playerIds,
            $leftIds,
        );
    }

    private function resolveLegNumberForUndo(\App\Models\QuickGame\QuickGameFfaSession $session): int
    {
        $legNumber = (int) $session->current_leg_number;

        if ($this->visitRepository->getActiveForLeg($session, $legNumber)->isNotEmpty()) {
            return $legNumber;
        }

        if ($legNumber > 1) {
            return $legNumber - 1;
        }

        return $legNumber;
    }

    /**
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $leftIds
     */
    private function normalizeTurnIndicesForLeftPlayers(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        array $playerIds,
        array $leftIds,
    ): void {
        if ($leftIds === []) {
            return;
        }

        $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->current_player_index,
            $playerIds,
            $leftIds,
        );
        $session->leg_opener_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function broadcastStateForSession(\App\Models\QuickGame\QuickGameFfaSession $session, ?int $userId): array
    {
        $session->loadMissing('lobby');
        $gameType = strtolower((string) $session->game_type);
        if ($gameType === MatchFormat::GAME_TYPE_CRICKET) {
            $state = $this->cricketScoringService->getState((int) $session->lobby_id, $userId);
            broadcast(new QuickGameFfaStateUpdated($session->lobby_id, $state));

            return $state;
        }
        if ($gameType === MatchFormat::GAME_TYPE_BOB27) {
            $state = $this->bob27ScoringService->getState((int) $session->lobby_id, $userId);
            broadcast(new QuickGameFfaStateUpdated($session->lobby_id, $state));

            return $state;
        }

        $this->syncStalePresence($session);
        $visits = $this->visitRepository->getActiveForSession($session);
        $presence = $this->buildPresencePayload($session);
        $state = $this->stateBuilder->build($session, $visits, $userId, $presence);
        broadcast(new QuickGameFfaStateUpdated($session->lobby_id, $state));

        return $state;
    }

    private function syncStalePresence(\App\Models\QuickGame\QuickGameFfaSession $session): void
    {
        if (! $session->isInProgress()) {
            return;
        }

        // Na jednym urządzeniu host wpisuje wszystkich — heartbeat innych nie ma znaczenia.
        if ($session->scoring_mode === 'one_device') {
            return;
        }

        $playerIds = array_map('intval', $session->player_order ?? []);
        $trackableIds = $this->heartbeatTrackedPlayerIds($playerIds);
        if ($trackableIds === []) {
            return;
        }

        $this->presenceRepository->markStaleAsDisconnected(
            $session,
            $trackableIds,
            QuickGameFfaPresenceService::HEARTBEAT_TIMEOUT_SECONDS,
        );
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

        $guestIds = $this->playerRepository->getGuestPlayerIds($playerIds);

        return FfaSessionRulesDomain::heartbeatTrackedPlayerIds($playerIds, $guestIds);
    }

    /**
     * @return array<int, array{playerId: int, name: string, status: string}>
     */
    private function buildPresencePayload(\App\Models\QuickGame\QuickGameFfaSession $session): array
    {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $records = $this->presenceRepository->getForSession($session)->keyBy('player_id');
        $payload = [];

        foreach ($playerIds as $playerId) {
            $record = $records->get($playerId);
            $player = $record?->player;
            $isGuestWithoutAccount = $player !== null && $player->user_id === null;
            $status = FfaSessionRulesDomain::effectivePresenceStatus(
                $isGuestWithoutAccount,
                $record?->status ?? QuickGameFfaPresence::STATUS_CONNECTED,
            );
            $payload[] = [
                'playerId' => $playerId,
                'name' => $player?->name ?? 'Gracz',
                'status' => $status,
            ];
        }

        return $payload;
    }
}
