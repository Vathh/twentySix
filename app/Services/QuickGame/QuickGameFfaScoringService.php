<?php

namespace App\Services\QuickGame;

use App\Domain\GameScoring\DartLimitRules;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\GameScoring\MatchFormatScoring;
use App\Domain\GameScoring\VisitRecorder;
use App\Domain\QuickGame\AroundTheClockRules;
use App\Domain\QuickGame\Bob27Rules;
use App\Domain\QuickGame\Catch40Rules;
use App\Domain\QuickGame\Cricket56Rules;
use App\Domain\QuickGame\CricketRules;
use App\Domain\QuickGame\FfaLegCycle;
use App\Domain\QuickGame\FfaSessionRulesDomain;
use App\Domain\QuickGame\FfaTurnRotationDomain;
use App\DTO\QuickGame\PlayerResultDTO;
use App\DTO\QuickGameFfa\RecordFfaVisitDTO;
use App\Models\QuickGame\QuickGameFfaPresence;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\QuickGame\QuickGameLobby;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameFfaVisitRepository;
use App\Support\QuickGameFfa\FfaStateBroadcaster;
use App\Support\QuickGameFfa\FfaTurnNormalize;
use App\Support\QuickGameFfa\QuickGameFfaStateBuilder;
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
        private FfaMatchFinishService $matchFinishService,
        private QuickGameFfaCricketScoringService $cricketScoringService,
        private QuickGameFfaBob27ScoringService $bob27ScoringService,
        private QuickGameFfaAtcScoringService $atcScoringService,
        private QuickGameFfaCatch40ScoringService $catch40ScoringService,
        private QuickGameFfaCricket56ScoringService $cricket56ScoringService,
        private FfaSubmitGuard $submitGuard,
    ) {}

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
        $isAtc = $matchFormat->isAtc();
        $isCatch40 = $matchFormat->isCatch40();
        $isCricket56 = $matchFormat->isCricket56();
        $setsToWin = ($isCricket || $isBob27 || $isAtc || $isCatch40 || $isCricket56) ? 1 : $matchFormat->setsToWinMatch;
        $gameType = match (true) {
            $isCricket => MatchFormat::GAME_TYPE_CRICKET,
            $isBob27 => MatchFormat::GAME_TYPE_BOB27,
            $isAtc => MatchFormat::GAME_TYPE_ATC,
            $isCatch40 => MatchFormat::GAME_TYPE_CATCH40,
            $isCricket56 => MatchFormat::GAME_TYPE_CRICKET56,
            default => $matchFormat->gameType,
        };

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
                ? Bob27Rules::initialState(
                    $playerIds,
                    $matchFormat->bob27Mode,
                    $matchFormat->includesBob27Bull(),
                )
                : null,
            'atc_state' => $isAtc
                ? AroundTheClockRules::initialState($playerIds)
                : null,
            'catch40_state' => $isCatch40
                ? Catch40Rules::initialState($playerIds)
                : null,
            'cricket56_state' => $isCricket56
                ? Cricket56Rules::initialState($playerIds)
                : null,
            'leg_opener_index' => 0,
            'current_player_index' => 0,
            'current_leg_number' => 1,
            'current_set_number' => 1,
            'state_version' => 1,
            'started_at' => now(),
            'dart_limit' => ($scoringMode === 'each_own' || ! $matchFormat->isX01())
                ? null
                : $matchFormat->dartLimit,
            'loss_threshold' => null,
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
        if (strtolower((string) $session->game_type) === MatchFormat::GAME_TYPE_ATC) {
            return $this->atcScoringService->getState($lobbyId, $userId);
        }
        if (strtolower((string) $session->game_type) === MatchFormat::GAME_TYPE_CATCH40) {
            return $this->catch40ScoringService->getState($lobbyId, $userId);
        }
        if (strtolower((string) $session->game_type) === MatchFormat::GAME_TYPE_CRICKET56) {
            return $this->cricket56ScoringService->getState($lobbyId, $userId);
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
     * Host unieważnia grę — klienci dostają aborted zanim lobby zniknie z bazy.
     *
     * @return array<string, mixed>
     */
    public function abortAndBroadcast(int $lobbyId): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->status = QuickGameFfaSession::STATUS_ABORTED;
        $session->finished_at = now();
        $this->sessionRepository->incrementVersion($session);
        $this->sessionRepository->save($session);

        $state = [
            'game' => ['status' => QuickGameFfaSession::STATUS_ABORTED],
            'session' => [
                'status' => QuickGameFfaSession::STATUS_ABORTED,
                'lobbyId' => $lobbyId,
                'stateVersion' => (int) $session->state_version,
            ],
            'players' => [],
        ];

        return FfaStateBroadcaster::emit($lobbyId, $state);
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
                throw new DomainException('Sesja cricket — użyj endpointu /ffa/cricket/visits.');
            }
            if ($gameType === MatchFormat::GAME_TYPE_BOB27) {
                throw new DomainException('Sesja Bob\'s 27 — użyj endpointu /ffa/bob27/darts.');
            }
            if ($gameType === MatchFormat::GAME_TYPE_ATC) {
                throw new DomainException('Sesja Around the Clock — użyj endpointu /ffa/atc/visits.');
            }
            if ($gameType === MatchFormat::GAME_TYPE_CATCH40) {
                throw new DomainException('Sesja Catch 40 — użyj endpointu /ffa/catch40/visits.');
            }
            if ($gameType === MatchFormat::GAME_TYPE_CRICKET56) {
                throw new DomainException('Sesja Cricket 60 — użyj endpointu /ffa/cricket56/visits.');
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

            if ($this->isBullOffPending($session, $playerIds, $leftIds) && ! $dto->closedLeg) {
                throw new DomainException('Najpierw rozstrzygnij rzut do bulla albo cofnij ostatnią kolejkę.');
            }

            $currentPlayerId = (int) $playerIds[$session->current_player_index];
            if ($dto->playerId !== $currentPlayerId) {
                throw new DomainException('Teraz rzuca inny gracz.');
            }

            $this->submitGuard->assert($session, $userId, $dto->playerId);

            VisitRecorder::validateDto($dto, (int) $session->starting_score);

            $legVisits = $this->visitRepository
                ->getActiveForLeg($session, (int) $session->current_leg_number)
                ->where('player_id', $dto->playerId);
            if ($existing !== null) {
                $legVisits = $legVisits->reject(fn ($visit) => (int) $visit->id === (int) $existing->id);
            }
            VisitRecorder::assertRemainingBeforeMatchesServer(
                (int) $dto->remainingBefore,
                VisitRecorder::remainingFromLegVisits($legVisits, (int) $session->starting_score),
            );

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
    public function closeLegByBullOff(int $lobbyId, int $userId, int $winnerPlayerId): array
    {
        return DB::transaction(function () use ($lobbyId, $userId, $winnerPlayerId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $format = MatchFormat::fromRecord($session);
            $scoringMode = (string) $session->scoring_mode;
            if (! DartLimitRules::isApplicable($format->dartLimit, $format->isX01(), $scoringMode)) {
                throw new DomainException('Rzut do bulla jest dostępny tylko przy ograniczniku lotek na jednym urządzeniu.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);
            if (! in_array($winnerPlayerId, $playerIds, true)) {
                throw new DomainException('Zwycięzca lega musi być uczestnikiem meczu.');
            }
            if (in_array($winnerPlayerId, $leftIds, true)) {
                throw new DomainException('Ten gracz opuścił mecz.');
            }

            $this->submitGuard->assert($session, $userId, null);

            if (! $this->isBullOffPending($session, $playerIds, $leftIds)) {
                throw new DomainException('Nie wszyscy zawodnicy osiągnęli limit lotek.');
            }

            $legNumber = (int) $session->current_leg_number;
            $legVisits = $this->visitRepository->getActiveForLeg($session, $legNumber);
            $remaining = VisitRecorder::remainingFromLegVisits(
                $legVisits->where('player_id', $winnerPlayerId),
                (int) $session->starting_score,
            );
            $this->visitRepository->createBullOffClose($session, $legNumber, $winnerPlayerId, $remaining);
            $this->advanceAfterLegClosed($session, $winnerPlayerId, $playerIds, $leftIds);
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastStateForSession($session->fresh(), $userId);
        });
    }

    /**
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $leftIds
     */
    private function isBullOffPending(
        \App\Models\QuickGame\QuickGameFfaSession $session,
        array $playerIds,
        array $leftIds,
    ): bool {
        $format = MatchFormat::fromRecord($session);
        if (! DartLimitRules::isApplicable($format->dartLimit, $format->isX01(), (string) $session->scoring_mode)) {
            return false;
        }

        $activeIds = array_values(array_filter(
            $playerIds,
            static fn (int $id): bool => ! in_array($id, $leftIds, true),
        ));
        $visits = $this->visitRepository->getActiveForLeg($session, (int) $session->current_leg_number);
        $darts = DartLimitRules::dartsByPlayerId($visits, $activeIds);

        return DartLimitRules::isReached($format->dartLimit, $darts);
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

            $this->submitGuard->assert($session, $userId, null);

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

        // Kompletna wizyta (w tym bust) — tura przechodzi dalej, chyba że zbito limit lotek.
        if ($this->isBullOffPending($session, $playerIds, $leftIds)) {
            return;
        }

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

        FfaLegCycle::startNextLeg($session, $playerIds, $leftIds);
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

        $ranked = $this->matchFinishService->rankedByLegsWon(
            array_map('intval', $playerIds),
            $legsWon,
        );

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

        $this->matchFinishService->persist($session, $format, $results, $legsWon);
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
        FfaTurnNormalize::apply($session, $playerIds, $leftIds);
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
            return FfaStateBroadcaster::emit(
                (int) $session->lobby_id,
                $this->cricketScoringService->getState((int) $session->lobby_id, $userId),
            );
        }
        if ($gameType === MatchFormat::GAME_TYPE_BOB27) {
            return FfaStateBroadcaster::emit(
                (int) $session->lobby_id,
                $this->bob27ScoringService->getState((int) $session->lobby_id, $userId),
            );
        }
        if ($gameType === MatchFormat::GAME_TYPE_ATC) {
            return FfaStateBroadcaster::emit(
                (int) $session->lobby_id,
                $this->atcScoringService->getState((int) $session->lobby_id, $userId),
            );
        }
        if ($gameType === MatchFormat::GAME_TYPE_CATCH40) {
            return FfaStateBroadcaster::emit(
                (int) $session->lobby_id,
                $this->catch40ScoringService->getState((int) $session->lobby_id, $userId),
            );
        }
        if ($gameType === MatchFormat::GAME_TYPE_CRICKET56) {
            return FfaStateBroadcaster::emit(
                (int) $session->lobby_id,
                $this->cricket56ScoringService->getState((int) $session->lobby_id, $userId),
            );
        }

        $this->syncStalePresence($session);
        $visits = $this->visitRepository->getActiveForSession($session);
        $presence = $this->buildPresencePayload($session);
        $state = $this->stateBuilder->build($session, $visits, $userId, $presence);

        return FfaStateBroadcaster::emit((int) $session->lobby_id, $state);
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
