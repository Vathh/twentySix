<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\PlayerResultDTO;
use App\Events\QuickGameFfaStateUpdated;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Support\GameScoring\MatchFormat;
use App\Support\QuickGameFfa\CricketRules;
use App\Support\QuickGameFfa\QuickGameFfaTurnRotation;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Scoring cricket FFA — osobny kontrakt od wizyt X01.
 */
class QuickGameFfaCricketScoringService
{
    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaPresenceRepository $presenceRepository,
        private PlayerRepository $playerRepository,
        private QuickGameRepository $quickGameRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(int $lobbyId, ?int $userId = null): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->loadMissing('lobby');
        $this->assertCricketSession($session);

        return $this->buildState($session, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDart(
        int $lobbyId,
        int $userId,
        int $playerId,
        string $kind,
        ?string $segment,
        int $multiplier,
        string $clientDartId,
    ): array {
        return DB::transaction(function () use ($lobbyId, $userId, $playerId, $kind, $segment, $multiplier, $clientDartId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');
            $this->assertCricketSession($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);

            if (! in_array($playerId, $playerIds, true)) {
                throw new DomainException('Gracz nie należy do tego meczu.');
            }
            if (in_array($playerId, $leftIds, true)) {
                throw new DomainException('Ten gracz opuścił mecz.');
            }

            $this->normalizeTurnIndices($session, $playerIds, $leftIds);

            $currentPlayerId = (int) $playerIds[(int) $session->current_player_index];
            if ($playerId !== $currentPlayerId) {
                throw new DomainException('Teraz rzuca inny gracz.');
            }

            $this->assertCanSubmit($session, $userId, $playerId);

            $state = $this->normalizeCricketState($session, $playerIds);
            foreach ($state['dartLog'] as $entry) {
                if (($entry['clientDartId'] ?? null) === $clientDartId) {
                    return $this->broadcastState($session->fresh(), $userId);
                }
            }

            $playerIndex = array_search($playerId, $playerIds, true);
            if ($playerIndex === false) {
                throw new DomainException('Nieprawidłowy gracz.');
            }
            $playerIndex = (int) $playerIndex;

            if ($kind === 'miss') {
                $state['dartLog'][] = [
                    'playerId' => $playerId,
                    'kind' => 'miss',
                    'dartsInVisitBefore' => (int) $state['dartsInVisit'],
                    'clientDartId' => $clientDartId,
                    'legNumber' => (int) $session->current_leg_number,
                    'legOpenerIndex' => (int) $session->leg_opener_index,
                    'currentPlayerIndex' => (int) $session->current_player_index,
                    'boardsSnapshot' => $state['boards'],
                    'legsWonSnapshot' => $session->legs_won_in_set,
                ];
                $this->advanceAfterDart($session, $state, $playerIds, $leftIds, null);
            } else {
                if ($segment === null || ! CricketRules::isValidSegment($segment)) {
                    throw new DomainException('Nieprawidłowy segment.');
                }
                $mult = max(1, min(3, $multiplier));
                if (CricketRules::segmentKey($segment) === 'bull' && $mult > 2) {
                    throw new DomainException('Bull nie ma triple.');
                }

                $hitsList = [];
                foreach ($playerIds as $i => $pid) {
                    $hitsList[$i] = $state['boards'][(string) $pid]['hits'] ?? CricketRules::emptyHits();
                }

                $applied = CricketRules::applyDart($hitsList, $playerIndex, $segment, $mult);
                $pidKey = (string) $playerId;
                $pointsBefore = (int) ($state['boards'][$pidKey]['points'] ?? 0);

                $state['dartLog'][] = [
                    'playerId' => $playerId,
                    'kind' => 'hit',
                    'segment' => CricketRules::segmentKey($segment),
                    'multiplier' => $mult,
                    'pointsScored' => $applied['pointsScored'],
                    'hitsBefore' => $state['boards'][$pidKey]['hits'],
                    'pointsBefore' => $pointsBefore,
                    'dartsInVisitBefore' => (int) $state['dartsInVisit'],
                    'clientDartId' => $clientDartId,
                    'legNumber' => (int) $session->current_leg_number,
                    'legOpenerIndex' => (int) $session->leg_opener_index,
                    'currentPlayerIndex' => (int) $session->current_player_index,
                    'boardsSnapshot' => $state['boards'],
                    'legsWonSnapshot' => $session->legs_won_in_set,
                ];

                $state['boards'][$pidKey] = [
                    'hits' => $applied['hits'],
                    'points' => $pointsBefore + $applied['pointsScored'],
                ];

                $boardsForWin = $this->boardsList($state, $playerIds);
                $winnerIdx = CricketRules::findLegWinnerIndex($boardsForWin);
                $this->advanceAfterDart($session, $state, $playerIds, $leftIds, $winnerIdx);
            }

            $session->cricket_state = $state;
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastState($session->fresh(), $userId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function undoLastDart(int $lobbyId, int $userId): array
    {
        return DB::transaction(function () use ($lobbyId, $userId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');
            $this->assertCricketSession($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $this->assertCanSubmit($session, $userId, null);

            $playerIds = array_map('intval', $session->player_order ?? []);
            $state = $this->normalizeCricketState($session, $playerIds);
            if ($state['dartLog'] === []) {
                throw new DomainException('Brak rzutu do cofnięcia.');
            }

            $last = array_pop($state['dartLog']);
            $state['boards'] = $last['boardsSnapshot'] ?? $state['boards'];
            $state['dartsInVisit'] = (int) ($last['dartsInVisitBefore'] ?? 0);
            $session->legs_won_in_set = $last['legsWonSnapshot'] ?? $session->legs_won_in_set;
            $session->current_leg_number = (int) ($last['legNumber'] ?? $session->current_leg_number);
            $session->leg_opener_index = (int) ($last['legOpenerIndex'] ?? $session->leg_opener_index);
            $session->current_player_index = (int) ($last['currentPlayerIndex'] ?? $session->current_player_index);
            $session->cricket_state = $state;

            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

            return $this->broadcastState($session->fresh(), $userId);
        });
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     * @param  list<int>  $leftIds
     */
    private function advanceAfterDart(
        QuickGameFfaSession $session,
        array &$state,
        array $playerIds,
        array $leftIds,
        ?int $legWinnerIndex,
    ): void {
        if ($legWinnerIndex !== null) {
            $winnerId = (int) $playerIds[$legWinnerIndex];
            $legsWon = $session->legs_won_in_set ?? [];
            foreach ($playerIds as $pid) {
                $legsWon[$pid] ??= 0;
            }
            $legsWon[$winnerId] = (int) ($legsWon[$winnerId] ?? 0) + 1;
            $session->legs_won_in_set = $legsWon;

            $format = MatchFormat::fromRecord($session);
            if ((int) $legsWon[$winnerId] >= $format->legsToWinSet) {
                $this->finishMatch($session, $legsWon, $format, $state);
                $state['dartsInVisit'] = 0;
                $state['dartLog'] = [];

                return;
            }

            // Nowy leg — reset tablic, rotacja openera
            $state['boards'] = CricketRules::initialState($playerIds)['boards'];
            $state['dartsInVisit'] = 0;
            $state['dartLog'] = [];
            $session->leg_opener_index = QuickGameFfaTurnRotation::nextIndexAfter(
                (int) $session->leg_opener_index,
                $playerIds,
                $leftIds,
            );
            $session->current_player_index = (int) $session->leg_opener_index;
            $session->current_leg_number = (int) $session->current_leg_number + 1;

            return;
        }

        $nextDarts = (int) $state['dartsInVisit'] + 1;
        if ($nextDarts >= 3) {
            $state['dartsInVisit'] = 0;
            $session->current_player_index = QuickGameFfaTurnRotation::nextIndexAfter(
                (int) $session->current_player_index,
                $playerIds,
                $leftIds,
            );
        } else {
            $state['dartsInVisit'] = $nextDarts;
        }
    }

    /**
     * @param  array<int, int>  $legsWon
     * @param  array<string, mixed>  $state
     */
    private function finishMatch(
        QuickGameFfaSession $session,
        array $legsWon,
        MatchFormat $format,
        array $state,
    ): void {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $ranked = collect($playerIds)
            ->map(fn ($pid) => ['playerId' => (int) $pid, 'score' => (int) ($legsWon[$pid] ?? 0)])
            ->sortByDesc('score')
            ->values();

        $dartCounts = [];
        $pointsTotals = [];
        foreach ($playerIds as $pid) {
            $dartCounts[$pid] = 0;
            $pointsTotals[$pid] = 0;
        }
        foreach ($state['dartLog'] ?? [] as $entry) {
            $pid = (int) ($entry['playerId'] ?? 0);
            if (! isset($dartCounts[$pid])) {
                continue;
            }
            $dartCounts[$pid]++;
            $pointsTotals[$pid] += (int) ($entry['pointsScored'] ?? 0);
        }
        // + punkty z bieżącej tablicy (ostatni leg)
        foreach ($playerIds as $pid) {
            $pointsTotals[$pid] = max(
                $pointsTotals[$pid],
                (int) ($state['boards'][(string) $pid]['points'] ?? 0),
            );
        }

        $results = [];
        foreach ($ranked as $i => $row) {
            $pid = $row['playerId'];
            $darts = $dartCounts[$pid] ?: null;
            $pts = $pointsTotals[$pid] ?: null;
            $results[] = new PlayerResultDTO(
                playerId: $pid,
                score: $row['score'],
                place: $i + 1,
                average: null,
                dartsThrown: $darts,
                pointsEarned: $pts,
            );
        }

        $quickGameId = $this->quickGameRepository->createWithResults($playerIds, $session->lobby_id);
        $this->quickGameRepository->saveResults($quickGameId, $results);

        $winnerId = $ranked->first()['playerId'] ?? null;
        $p1 = $playerIds[0] ?? null;
        $p2 = $playerIds[1] ?? null;

        \App\Models\QuickGame\QuickGame::where('id', $quickGameId)->update(array_merge(
            [
                'player1_score' => (int) ($legsWon[$p1] ?? 0),
                'player2_score' => (int) ($legsWon[$p2] ?? 0),
                'winner_id' => $winnerId,
                'status' => \App\Enums\GameStatus::FINISHED,
            ],
            $format->toDatabaseColumns(),
        ));

        $session->status = QuickGameFfaSession::STATUS_FINISHED;
        $session->quick_game_id = $quickGameId;
        $session->finished_at = now();

        $session->loadMissing('lobby');
        $lobby = $session->lobby;
        if ($lobby !== null) {
            $lobby->status = 'finished';
            $lobby->quick_game_id = $quickGameId;
            $lobby->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcastState(QuickGameFfaSession $session, ?int $userId): array
    {
        $state = $this->buildState($session, $userId);
        broadcast(new QuickGameFfaStateUpdated($session->lobby_id, $state));

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildState(QuickGameFfaSession $session, ?int $userId): array
    {
        $playerIds = array_map('intval', $session->player_order ?? []);
        $cricket = $this->normalizeCricketState($session, $playerIds);
        $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');
        $format = MatchFormat::fromRecord($session);
        $legsWon = $session->legs_won_in_set ?? [];

        $playerStates = [];
        foreach ($playerIds as $orderIndex => $playerId) {
            $board = $cricket['boards'][(string) $playerId] ?? [
                'hits' => CricketRules::emptyHits(),
                'points' => 0,
            ];
            $player = $players->get($playerId);
            $playerStates[] = [
                'playerId' => (int) $playerId,
                'name' => $player?->name ?? 'Gracz',
                'orderIndex' => $orderIndex,
                'legsWon' => (int) ($legsWon[$playerId] ?? 0),
                'hits' => $board['hits'],
                'points' => (int) ($board['points'] ?? 0),
            ];
        }

        $myPlayerIndex = null;
        $canInput = false;
        if ($userId !== null) {
            $lobby = $session->lobby;
            if ($session->scoring_mode === 'one_device') {
                $canInput = $lobby !== null && (int) $lobby->host_id === $userId;
                if ($canInput) {
                    $myPlayerIndex = (int) $session->current_player_index;
                }
            } else {
                $me = $this->playerRepository->findByUserId($userId);
                if ($me !== null) {
                    $idx = array_search((int) $me->id, $playerIds, true);
                    if ($idx !== false) {
                        $myPlayerIndex = (int) $idx;
                        $canInput = $myPlayerIndex === (int) $session->current_player_index
                            && $session->isInProgress();
                    }
                }
            }
        }

        return [
            'format' => 'ffa_cricket',
            'meta' => [
                'kind' => 'quick_ffa_cricket',
                'lobbyId' => (int) $session->lobby_id,
            ],
            'session' => [
                'id' => $session->id,
                'lobbyId' => $session->lobby_id,
                'status' => $session->status,
                'legsToWinSet' => $format->legsToWinSet,
                'setsToWinMatch' => 1,
                'matchFormat' => array_merge($format->toArray(), [
                    'gameType' => 'cricket',
                    'setsToWinMatch' => 1,
                ]),
                'gameType' => 'cricket',
                'scoringMode' => $session->scoring_mode,
                'currentLegNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'dartsInVisit' => (int) ($cricket['dartsInVisit'] ?? 0),
                'stateVersion' => (int) $session->state_version,
                'quickGameId' => $session->quick_game_id,
            ],
            'players' => $playerStates,
            'turn' => [
                'currentPlayerIndex' => (int) $session->current_player_index,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'dartsInVisit' => (int) ($cricket['dartsInVisit'] ?? 0),
            ],
            'you' => [
                'canInput' => $canInput && $session->isInProgress(),
                'myPlayerIndex' => $myPlayerIndex,
            ],
            'game' => [
                'status' => $session->status === QuickGameFfaSession::STATUS_FINISHED
                    ? 'finished'
                    : 'in_progress',
            ],
        ];
    }

    private function assertCricketSession(QuickGameFfaSession $session): void
    {
        if (strtolower((string) $session->game_type) !== 'cricket') {
            throw new DomainException('To nie jest sesja cricket.');
        }
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    private function normalizeCricketState(QuickGameFfaSession $session, array $playerIds): array
    {
        $raw = $session->cricket_state;
        if (! is_array($raw) || ! isset($raw['boards'])) {
            return CricketRules::initialState($playerIds);
        }

        $boards = $raw['boards'];
        foreach ($playerIds as $pid) {
            $key = (string) $pid;
            if (! isset($boards[$key])) {
                $boards[$key] = [
                    'hits' => CricketRules::emptyHits(),
                    'points' => 0,
                ];
            }
        }

        return [
            'boards' => $boards,
            'dartsInVisit' => (int) ($raw['dartsInVisit'] ?? 0),
            'dartLog' => is_array($raw['dartLog'] ?? null) ? $raw['dartLog'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     * @return list<array{hits: array<string, int>, points: int}>
     */
    private function boardsList(array $state, array $playerIds): array
    {
        $list = [];
        foreach ($playerIds as $pid) {
            $b = $state['boards'][(string) $pid] ?? null;
            $list[] = [
                'hits' => $b['hits'] ?? CricketRules::emptyHits(),
                'points' => (int) ($b['points'] ?? 0),
            ];
        }

        return $list;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $leftIds
     */
    private function normalizeTurnIndices(QuickGameFfaSession $session, array $playerIds, array $leftIds): void
    {
        if ($leftIds === []) {
            return;
        }
        $session->current_player_index = QuickGameFfaTurnRotation::normalizeIndexAt(
            (int) $session->current_player_index,
            $playerIds,
            $leftIds,
        );
        $session->leg_opener_index = QuickGameFfaTurnRotation::normalizeIndexAt(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
    }

    private function assertCanSubmit(QuickGameFfaSession $session, int $userId, ?int $dartPlayerId): void
    {
        $lobby = $session->lobby;
        if ($lobby === null) {
            throw new DomainException('Lobby nie istnieje.');
        }

        if ($session->scoring_mode === 'one_device') {
            if ((int) $lobby->host_id !== $userId) {
                throw new DomainException('W trybie jednego urządzenia punkty wpisuje tylko host.');
            }

            return;
        }

        $player = $this->playerRepository->findByUserId($userId);
        if ($player === null) {
            throw new DomainException('Brak profilu gracza.');
        }

        if ($dartPlayerId !== null && (int) $player->id !== $dartPlayerId) {
            throw new DomainException('Możesz wpisywać tylko własne rzuty.');
        }

        $playerIds = array_map('intval', $session->player_order ?? []);
        if (! in_array((int) $player->id, $playerIds, true)) {
            throw new DomainException('Nie jesteś uczestnikiem tego meczu.');
        }
    }
}
