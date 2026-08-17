<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\PlayerResultDTO;
use App\Domain\GameScoring\MatchFormat;
use App\Domain\QuickGame\Cricket56Rules;
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
 * Scoring Cricket 60 FFA — 7 rund 15–20 + bull.
 */
class QuickGameFfaCricket56ScoringService
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
        $this->assertCricket56Session($session);

        return $this->buildState($session, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVisit(
        int $lobbyId,
        int $userId,
        int $playerId,
        int $points,
        string $clientVisitId,
    ): array {
        return DB::transaction(function () use ($lobbyId, $userId, $playerId, $points, $clientVisitId) {
            $session = $this->sessionRepository->findOrFailForLobby($lobbyId);
            $session->loadMissing('lobby');
            $this->assertCricket56Session($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $playerIds = array_map('intval', $session->player_order ?? []);
            $leftIds = $this->presenceRepository->getLeftPlayerIds($session);
            $state = $this->normalizeState($session, $playerIds);
            $skipIds = $leftIds;
            $points = Cricket56Rules::clampPoints($points, (int) $state['currentRoundIndex']);

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
                if (($entry['clientDartId'] ?? $entry['clientVisitId'] ?? null) === $clientVisitId) {
                    return $this->broadcastState($session->fresh(), $userId);
                }
            }

            $playerIndex = (int) array_search($playerId, $playerIds, true);

            $state['dartLog'][] = [
                'playerId' => $playerId,
                'kind' => 'visit',
                'points' => $points,
                'clientDartId' => $clientVisitId,
                'clientVisitId' => $clientVisitId,
                'legNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'currentRoundIndex' => (int) $state['currentRoundIndex'],
                'thrownThisRound' => $state['thrownThisRound'],
                'boardsSnapshot' => $state['boards'],
                'legsWonSnapshot' => $session->legs_won_in_set,
            ];

            $this->applyCompletedVisit($session, $state, $playerIds, $leftIds, $playerIndex, $points);

            $session->cricket56_state = $state;
            $this->sessionRepository->incrementVersion($session);
            $this->sessionRepository->save($session);

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
            $this->assertCricket56Session($session);

            if (! $session->isInProgress()) {
                throw new DomainException('Mecz jest już zakończony.');
            }

            $this->assertCanSubmit($session, $userId, null);

            $playerIds = array_map('intval', $session->player_order ?? []);
            $state = $this->normalizeState($session, $playerIds);
            if ($state['dartLog'] === []) {
                throw new DomainException('Brak wizyty do cofnięcia.');
            }

            $last = array_pop($state['dartLog']);
            $state['boards'] = $last['boardsSnapshot'] ?? $state['boards'];
            $state['currentRoundIndex'] = (int) ($last['currentRoundIndex'] ?? 0);
            $state['thrownThisRound'] = is_array($last['thrownThisRound'] ?? null)
                ? $last['thrownThisRound']
                : [];
            $session->legs_won_in_set = $last['legsWonSnapshot'] ?? $session->legs_won_in_set;
            $session->current_leg_number = (int) ($last['legNumber'] ?? $session->current_leg_number);
            $session->leg_opener_index = (int) ($last['legOpenerIndex'] ?? $session->leg_opener_index);
            $session->current_player_index = (int) ($last['currentPlayerIndex'] ?? $session->current_player_index);
            $session->cricket56_state = $state;

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
    private function applyCompletedVisit(
        QuickGameFfaSession $session,
        array &$state,
        array $playerIds,
        array $leftIds,
        int $playerIndex,
        int $points,
    ): void {
        $pidKey = (string) $playerIds[$playerIndex];
        $board = $state['boards'][$pidKey] ?? Cricket56Rules::emptyBoard();
        $scoreAfter = Cricket56Rules::applyVisit(
            (int) ($board['score'] ?? 0),
            $points,
            (int) $state['currentRoundIndex'],
        );
        $state['boards'][$pidKey] = ['score' => $scoreAfter];

        $thrown = $state['thrownThisRound'];
        $thrown[$playerIndex] = true;
        $state['thrownThisRound'] = $thrown;

        $boardsList = $this->boardsList($state, $playerIds);
        $leftIndices = $this->leftIndices($playerIds, $leftIds);
        $outcome = Cricket56Rules::resolveAfterCompletedVisit(
            $boardsList,
            (int) $state['currentRoundIndex'],
            $thrown,
            $leftIndices,
        );

        if ($outcome['kind'] === Cricket56Rules::KIND_WIN) {
            $this->closeLeg($session, $state, $playerIds, $leftIds, (int) $outcome['winnerIndex']);

            return;
        }

        if ($outcome['kind'] === Cricket56Rules::KIND_TIE_RESET) {
            $this->resetBoard($state, $playerIds);
            $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
                (int) $session->leg_opener_index,
                $playerIds,
                $leftIds,
            );

            return;
        }

        $allThrown = Cricket56Rules::allActiveHaveThrown($boardsList, $thrown, $leftIndices);
        if ($allThrown) {
            $state['currentRoundIndex'] = (int) $state['currentRoundIndex'] + 1;
            $state['thrownThisRound'] = [];
        }

        $state['dartsInVisit'] = 0;
        $session->current_player_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->current_player_index,
            $playerIds,
            $leftIds,
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
            ['gameType' => MatchFormat::GAME_TYPE_CRICKET56],
        ));
        if ((int) $legsWon[$winnerId] >= $format->legsToWinSet) {
            $this->finishMatch($session, $legsWon, $format, $state);
            $state['dartLog'] = [];

            return;
        }

        $this->resetBoard($state, $playerIds);
        $state['dartLog'] = [];
        $session->leg_opener_index = FfaTurnRotationDomain::nextIndexAfter(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
        $session->current_player_index = FfaTurnRotationDomain::normalizeIndexAt(
            (int) $session->leg_opener_index,
            $playerIds,
            $leftIds,
        );
        $session->current_leg_number = (int) $session->current_leg_number + 1;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     */
    private function resetBoard(array &$state, array $playerIds): void
    {
        $fresh = Cricket56Rules::initialState($playerIds);
        $state['boards'] = $fresh['boards'];
        $state['currentRoundIndex'] = 0;
        $state['dartsInVisit'] = 0;
        $state['thrownThisRound'] = [];
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
            if (! isset($dartCounts[$pid])) {
                continue;
            }
            $dartCounts[$pid] += 3;
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
        $cricket56 = $this->normalizeState($session, $playerIds);
        $players = $this->playerRepository->findManyByIds($playerIds)->keyBy('id');
        $format = MatchFormat::fromArray(array_merge(
            MatchFormat::fromRecord($session)->toArray(),
            [
                'gameType' => MatchFormat::GAME_TYPE_CRICKET56,
                'setsToWinMatch' => 1,
            ],
        ));
        $legsWon = $session->legs_won_in_set ?? [];
        $roundIndex = (int) $cricket56['currentRoundIndex'];

        $playerStates = [];
        foreach ($playerIds as $orderIndex => $playerId) {
            $board = $cricket56['boards'][(string) $playerId] ?? Cricket56Rules::emptyBoard();
            $player = $players->get($playerId);
            $playerStates[] = [
                'playerId' => (int) $playerId,
                'name' => $player?->name ?? 'Gracz',
                'orderIndex' => $orderIndex,
                'legsWon' => (int) ($legsWon[$playerId] ?? 0),
                'score' => (int) ($board['score'] ?? 0),
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
            'format' => 'ffa_cricket56',
            'meta' => [
                'kind' => 'quick_ffa_cricket56',
                'lobbyId' => (int) $session->lobby_id,
            ],
            'session' => [
                'id' => $session->id,
                'lobbyId' => $session->lobby_id,
                'status' => $session->status,
                'legsToWinSet' => $format->legsToWinSet,
                'setsToWinMatch' => 1,
                'matchFormat' => $format->toArray(),
                'gameType' => MatchFormat::GAME_TYPE_CRICKET56,
                'scoringMode' => $session->scoring_mode,
                'currentLegNumber' => (int) $session->current_leg_number,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'currentPlayerIndex' => (int) $session->current_player_index,
                'currentRoundIndex' => $roundIndex,
                'currentTargetLabel' => Cricket56Rules::targetLabel($roundIndex),
                'dartsInVisit' => (int) ($cricket56['dartsInVisit'] ?? 0),
                'stateVersion' => (int) $session->state_version,
                'quickGameId' => $session->quick_game_id,
            ],
            'players' => $playerStates,
            'turn' => [
                'currentPlayerIndex' => (int) $session->current_player_index,
                'legOpenerIndex' => (int) $session->leg_opener_index,
                'dartsInVisit' => (int) ($cricket56['dartsInVisit'] ?? 0),
                'currentRoundIndex' => $roundIndex,
                'currentTargetLabel' => Cricket56Rules::targetLabel($roundIndex),
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

    private function assertCricket56Session(QuickGameFfaSession $session): void
    {
        if (strtolower((string) $session->game_type) !== MatchFormat::GAME_TYPE_CRICKET56) {
            throw new DomainException('To nie jest sesja Cricket 60.');
        }
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    private function normalizeState(QuickGameFfaSession $session, array $playerIds): array
    {
        $raw = $session->cricket56_state;
        if (! is_array($raw) || ! isset($raw['boards'])) {
            return Cricket56Rules::initialState($playerIds);
        }

        $boards = $raw['boards'];
        foreach ($playerIds as $pid) {
            $key = (string) $pid;
            if (! isset($boards[$key])) {
                $boards[$key] = Cricket56Rules::emptyBoard();
            }
        }

        return [
            'currentRoundIndex' => (int) ($raw['currentRoundIndex'] ?? 0),
            'dartsInVisit' => (int) ($raw['dartsInVisit'] ?? 0),
            'thrownThisRound' => is_array($raw['thrownThisRound'] ?? null) ? $raw['thrownThisRound'] : [],
            'boards' => $boards,
            'dartLog' => is_array($raw['dartLog'] ?? null) ? $raw['dartLog'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<int>  $playerIds
     * @return list<array{score: int}>
     */
    private function boardsList(array $state, array $playerIds): array
    {
        $list = [];
        foreach ($playerIds as $pid) {
            $b = $state['boards'][(string) $pid] ?? Cricket56Rules::emptyBoard();
            $list[] = ['score' => (int) ($b['score'] ?? 0)];
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
