<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\PlayerResultDTO;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\QuickGame\Bob27Rules;
use App\Domain\QuickGame\FfaTurnRotationDomain;
use App\Events\QuickGameFfaStateUpdated;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameLobbyRepository;
use App\Repositories\QuickGame\QuickGameRepository;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Scoring Bob's 27 FFA — osobny kontrakt od wizyt X01 i cricket.
 */
class QuickGameFfaBob27ScoringService
{
    public function __construct(
        private QuickGameFfaSessionRepository $sessionRepository,
        private QuickGameFfaPresenceRepository $presenceRepository,
        private PlayerRepository $playerRepository,
        private QuickGameRepository $quickGameRepository,
        private QuickGameLobbyRepository $lobbyRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(int $lobbyId, ?int $userId = null): array
    {
        $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
        $session->loadMissing('lobby');
        $this->assertBob27Session($session);

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
        string $clientDartId,
    ): array {
        return DB::transaction(function () use ($lobbyId, $userId, $playerId, $kind, $clientDartId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');
            $this->assertBob27Session($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);
            $state = $this->normalizeState($session, $playerIds);
            $skipIds = $this->skipIds($playerIds, $leftIds, $state);

            if (! in_array($playerId, $playerIds, true)) {
                throw new DomainException('Gracz nie należy do tego meczu.');
            }
            if (in_array($playerId, $skipIds, true)) {
                throw new DomainException('Ten gracz nie rzuca w tej turze.');
            }

            $this->normalizeTurnIndices($session, $playerIds, $skipIds);

            $currentPlayerId = (int) $playerIds[(int) $session->current_player_index];
            if ($playerId !== $currentPlayerId) {
                throw new DomainException('Teraz rzuca inny gracz.');
            }

            $this->assertCanSubmit($session, $userId, $playerId);

            foreach ($state['dartLog'] as $entry) {
                if (($entry['clientDartId'] ?? null) === $clientDartId) {
                    return $this->broadcastState($session->fresh(), $userId);
                }
            }

            $playerIndex = (int) array_search($playerId, $playerIds, true);
            $hit = $kind === 'hit';

            $state['dartLog'][] = [
                'playerId' => $playerId,
                'kind' => $hit ? 'hit' : 'miss',
                'dartsInVisitBefore' => (int) $state['dartsInVisit'],
                'hitsInVisitBefore' => (int) $state['hitsInVisit'],
                'clientDartId' => $clientDartId,
                'legNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'currentTargetIndex' => (int) $state['currentTargetIndex'],
                'thrownThisTarget' => $state['thrownThisTarget'],
                'boardsSnapshot' => $state['boards'],
                'legsWonSnapshot' => $session->legs_won_in_set,
            ];

            if ($hit) {
                $state['hitsInVisit'] = (int) $state['hitsInVisit'] + 1;
            }

            $this->advanceAfterDart($session, $state, $playerIds, $leftIds, $playerIndex);

            $session->bob27_state = $state;
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
            $this->assertBob27Session($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $this->assertCanSubmit($session, $userId, null);

            $playerIds = array_map('intval', $session->player_order ?? []);
            $state = $this->normalizeState($session, $playerIds);
            if ($state['dartLog'] === []) {
                throw new DomainException('Brak rzutu do cofnięcia.');
            }

            $last = array_pop($state['dartLog']);
            $state['boards'] = $last['boardsSnapshot'] ?? $state['boards'];
            $state['dartsInVisit'] = (int) ($last['dartsInVisitBefore'] ?? 0);
            $state['hitsInVisit'] = (int) ($last['hitsInVisitBefore'] ?? 0);
            $state['currentTargetIndex'] = (int) ($last['currentTargetIndex'] ?? 0);
            $state['thrownThisTarget'] = is_array($last['thrownThisTarget'] ?? null)
                ? $last['thrownThisTarget']
                : [];
            $session->legs_won_in_set = $last['legsWonSnapshot'] ?? $session->legs_won_in_set;
            $session->current_leg_number = (int) ($last['legNumber'] ?? $session->current_leg_number);
            $session->leg_opener_index = (int) ($last['legOpenerIndex'] ?? $session->leg_opener_index);
            $session->current_player_index = (int) ($last['currentPlayerIndex'] ?? $session->current_player_index);
            $session->bob27_state = $state;

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
        int $playerIndex,
    ): void {
        $nextDarts = (int) $state['dartsInVisit'] + 1;
        if ($nextDarts < 3) {
            $state['dartsInVisit'] = $nextDarts;

            return;
        }

        $pidKey = (string) $playerIds[$playerIndex];
        $board = $state['boards'][$pidKey] ?? Bob27Rules::emptyBoard();
        $scoreAfter = Bob27Rules::applyVisit(
            (int) ($board['score'] ?? Bob27Rules::STARTING_SCORE),
            (int) $state['hitsInVisit'],
            (int) $state['currentTargetIndex'],
        );
        $eliminated = Bob27Rules::shouldEliminate($scoreAfter, (string) $state['mode']);
        $state['boards'][$pidKey] = [
            'score' => $scoreAfter,
            'eliminated' => $eliminated,
        ];

        $thrown = $state['thrownThisTarget'];
        $thrown[$playerIndex] = true;
        $state['thrownThisTarget'] = $thrown;

        $boardsList = $this->boardsList($state, $playerIds);
        $leftIndices = $this->leftIndices($playerIds, $leftIds);
        $outcome = Bob27Rules::resolveAfterCompletedVisit(
            $boardsList,
            (string) $state['mode'],
            (int) $state['currentTargetIndex'],
            $thrown,
            $leftIndices,
        );

        if ($outcome['kind'] === Bob27Rules::KIND_WIN) {
            $this->closeLeg($session, $state, $playerIds, $leftIds, (int) $outcome['winnerIndex']);

            return;
        }

        if ($outcome['kind'] === Bob27Rules::KIND_BUST) {
            $this->finishMatch($session, $session->legs_won_in_set ?? [], MatchFormat::fromRecord($session), $state);
            $state['dartsInVisit'] = 0;
            $state['hitsInVisit'] = 0;
            $state['dartLog'] = [];

            return;
        }

        if ($outcome['kind'] === Bob27Rules::KIND_TIE_RESET) {
            $this->resetBoard($state, $playerIds);
            $state['dartsInVisit'] = 0;
            $state['hitsInVisit'] = 0;
            $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
                (int) $session->leg_opener_index,
                $playerIds,
                $this->skipIds($playerIds, $leftIds, $state),
            );

            return;
        }

        $allThrown = Bob27Rules::allActiveHaveThrown($boardsList, $thrown, $leftIndices);
        if ($allThrown) {
            $state['currentTargetIndex'] = (int) $state['currentTargetIndex'] + 1;
            $state['thrownThisTarget'] = [];
        }

        $state['dartsInVisit'] = 0;
        $state['hitsInVisit'] = 0;
        $skipIds = $this->skipIds($playerIds, $leftIds, $state);
        $session->current_player_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->current_player_index,
            $playerIds,
            $skipIds,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     * @param  list<int>  $leftIds
     */
    private function closeLeg(
        QuickGameFfaSession $session,
        array &$state,
        array $playerIds,
        array $leftIds,
        int $winnerIndex,
    ): void {
        $winnerId = (int) $playerIds[$winnerIndex];
        $legsWon = $session->legs_won_in_set ?? [];
        foreach ($playerIds as $pid) {
            $legsWon[$pid] ??= 0;
        }
        $legsWon[$winnerId] = (int) ($legsWon[$winnerId] ?? 0) + 1;
        $session->legs_won_in_set = $legsWon;

        $format = MatchFormat::fromArray(array_merge(
            MatchFormat::fromRecord($session)->toArray(),
            ['gameType' => MatchFormat::GAME_TYPE_BOB27, 'bob27Mode' => $state['mode']],
        ));
        if ((int) $legsWon[$winnerId] >= $format->legsToWinSet) {
            $this->finishMatch($session, $legsWon, $format, $state);
            $state['dartsInVisit'] = 0;
            $state['hitsInVisit'] = 0;
            $state['dartLog'] = [];

            return;
        }

        $this->resetBoard($state, $playerIds);
        $state['dartLog'] = [];
        $skipIds = $this->skipIds($playerIds, $leftIds, $state);
        $session->leg_opener_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
        $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->leg_opener_index,
            $playerIds,
            $skipIds,
        );
        $session->current_leg_number = (int) $session->current_leg_number + 1;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     */
    private function resetBoard(array &$state, array $playerIds): void
    {
        $fresh = Bob27Rules::initialState($playerIds, (string) $state['mode']);
        $state['boards'] = $fresh['boards'];
        $state['currentTargetIndex'] = 0;
        $state['dartsInVisit'] = 0;
        $state['hitsInVisit'] = 0;
        $state['thrownThisTarget'] = [];
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
        foreach ($playerIds as $pid) {
            $dartCounts[$pid] = 0;
        }
        foreach ($state['dartLog'] ?? [] as $entry) {
            $pid = (int) ($entry['playerId'] ?? 0);
            if (isset($dartCounts[$pid])) {
                $dartCounts[$pid]++;
            }
        }

        $results = [];
        foreach ($ranked as $i => $row) {
            $pid = $row['playerId'];
            $pts = (int) ($state['boards'][(string) $pid]['score'] ?? 0);
            $results[] = new PlayerResultDTO(
                playerId: $pid,
                score: $row['score'],
                place: $i + 1,
                average: null,
                dartsThrown: $dartCounts[$pid] ?: null,
                pointsEarned: max(0, $pts),
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

        $session->status = QuickGameFfaSession::STATUS_FINISHED;
        $session->quick_game_id = $quickGameId;
        $session->finished_at = now();

        $session->loadMissing('lobby');
        $lobby = $session->lobby;
        if ($lobby !== null) {
            $this->lobbyRepository->markFinished($lobby->id, $quickGameId);
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
        $bob = $this->normalizeState($session, $playerIds);
        $players = $this->playerRepository->findManyByIds($playerIds)->keyBy('id');
        $format = MatchFormat::fromArray(array_merge(
            MatchFormat::fromRecord($session)->toArray(),
            [
                'gameType' => MatchFormat::GAME_TYPE_BOB27,
                'bob27Mode' => $bob['mode'],
                'setsToWinMatch' => 1,
            ],
        ));
        $legsWon = $session->legs_won_in_set ?? [];
        $targetIndex = (int) $bob['currentTargetIndex'];

        $playerStates = [];
        foreach ($playerIds as $orderIndex => $playerId) {
            $board = $bob['boards'][(string) $playerId] ?? Bob27Rules::emptyBoard();
            $player = $players->get($playerId);
            $playerStates[] = [
                'playerId' => (int) $playerId,
                'name' => $player?->name ?? 'Gracz',
                'orderIndex' => $orderIndex,
                'legsWon' => (int) ($legsWon[$playerId] ?? 0),
                'score' => (int) ($board['score'] ?? Bob27Rules::STARTING_SCORE),
                'eliminated' => (bool) ($board['eliminated'] ?? false),
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
            'format' => 'ffa_bob27',
            'meta' => [
                'kind' => 'quick_ffa_bob27',
                'lobbyId' => (int) $session->lobby_id,
            ],
            'session' => [
                'id' => $session->id,
                'lobbyId' => $session->lobby_id,
                'status' => $session->status,
                'legsToWinSet' => $format->legsToWinSet,
                'setsToWinMatch' => 1,
                'matchFormat' => $format->toArray(),
                'gameType' => MatchFormat::GAME_TYPE_BOB27,
                'bob27Mode' => $bob['mode'],
                'scoringMode' => $session->scoring_mode,
                'currentLegNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'currentTargetIndex' => $targetIndex,
                'currentTargetLabel' => Bob27Rules::targetLabel($targetIndex),
                'currentTargetValue' => Bob27Rules::targetValue($targetIndex),
                'dartsInVisit' => (int) ($bob['dartsInVisit'] ?? 0),
                'hitsInVisit' => (int) ($bob['hitsInVisit'] ?? 0),
                'stateVersion' => (int) $session->state_version,
                'quickGameId' => $session->quick_game_id,
            ],
            'players' => $playerStates,
            'turn' => [
                'currentPlayerIndex' => (int) $session->current_player_index,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'dartsInVisit' => (int) ($bob['dartsInVisit'] ?? 0),
                'hitsInVisit' => (int) ($bob['hitsInVisit'] ?? 0),
                'currentTargetIndex' => $targetIndex,
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

    private function assertBob27Session(QuickGameFfaSession $session): void
    {
        if (strtolower((string) $session->game_type) !== MatchFormat::GAME_TYPE_BOB27) {
            throw new DomainException('To nie jest sesja Bob\'s 27.');
        }
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    private function normalizeState(QuickGameFfaSession $session, array $playerIds): array
    {
        $raw = $session->bob27_state;
        $mode = is_array($raw) ? Bob27Rules::normalizeMode((string) ($raw['mode'] ?? Bob27Rules::MODE_HARD)) : Bob27Rules::MODE_HARD;
        if (! is_array($raw) || ! isset($raw['boards'])) {
            return Bob27Rules::initialState($playerIds, $mode);
        }

        $boards = $raw['boards'];
        foreach ($playerIds as $pid) {
            $key = (string) $pid;
            if (! isset($boards[$key])) {
                $boards[$key] = Bob27Rules::emptyBoard();
            }
        }

        return [
            'mode' => $mode,
            'currentTargetIndex' => (int) ($raw['currentTargetIndex'] ?? 0),
            'dartsInVisit' => (int) ($raw['dartsInVisit'] ?? 0),
            'hitsInVisit' => (int) ($raw['hitsInVisit'] ?? 0),
            'thrownThisTarget' => is_array($raw['thrownThisTarget'] ?? null) ? $raw['thrownThisTarget'] : [],
            'boards' => $boards,
            'dartLog' => is_array($raw['dartLog'] ?? null) ? $raw['dartLog'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     * @return list<array{score: int, eliminated: bool}>
     */
    private function boardsList(array $state, array $playerIds): array
    {
        $list = [];
        foreach ($playerIds as $pid) {
            $b = $state['boards'][(string) $pid] ?? Bob27Rules::emptyBoard();
            $list[] = [
                'score' => (int) ($b['score'] ?? Bob27Rules::STARTING_SCORE),
                'eliminated' => (bool) ($b['eliminated'] ?? false),
            ];
        }

        return $list;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $leftIds
     * @return list<int>
     */
    private function leftIndices(array $playerIds, array $leftIds): array
    {
        $out = [];
        foreach ($playerIds as $i => $pid) {
            if (in_array((int) $pid, $leftIds, true)) {
                $out[] = (int) $i;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $leftIds
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function skipIds(array $playerIds, array $leftIds, array $state): array
    {
        $skip = $leftIds;
        foreach ($playerIds as $pid) {
            $board = $state['boards'][(string) $pid] ?? null;
            if (! empty($board['eliminated'])) {
                $skip[] = (int) $pid;
            }
        }

        return array_values(array_unique($skip));
    }

    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $skipIds
     */
    private function normalizeTurnIndices(QuickGameFfaSession $session, array $playerIds, array $skipIds): void
    {
        if ($skipIds === []) {
            return;
        }
        $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->current_player_index,
            $playerIds,
            $skipIds,
        );
        $session->leg_opener_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->leg_opener_index,
            $playerIds,
            $skipIds,
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
