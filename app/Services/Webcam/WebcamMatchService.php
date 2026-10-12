<?php

namespace App\Services\Webcam;

use App\Domain\GameScoring\MatchFormat;
use App\Domain\GameScoring\VisitRecorder;
use App\Enums\WebcamMatchStatus;
use App\Events\WebcamMatchSignal;
use App\Events\WebcamMatchStateUpdated;
use App\Models\Users\User;
use App\Models\Webcam\WebcamMatch;
use App\Repositories\Webcam\WebcamMatchRepository;
use App\Services\Friends\FriendshipService;
use App\Support\Http\DomainExceptionHttp;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class WebcamMatchService
{
    private const STARTING_SCORE = MatchFormat::DEFAULT_STARTING_SCORE;

    private const LEGS_TO_WIN = MatchFormat::DEFAULT_LEGS_TO_WIN_SET;

    public function __construct(
        private WebcamMatchRepository $matches,
        private FriendshipService $friendships,
    ) {}

    public function isParticipant(int $matchId, int $userId): bool
    {
        return $this->matches->isParticipant($matchId, $userId);
    }

    /**
     * @return array{matches: list<array<string, mixed>>, friends: list<array{userId: int, name: string}>}
     */
    public function indexData(int $userId): array
    {
        $matches = $this->matches->listForUser($userId)
            ->map(fn (WebcamMatch $match) => $this->summary($match, $userId))
            ->values()
            ->all();

        $friends = $this->friendships->getFriends($userId)
            ->map(fn ($friend) => [
                'userId' => $friend->friendId,
                'name' => $friend->friendPlayer->name,
            ])
            ->values()
            ->all();

        return [
            'matches' => $matches,
            'friends' => $friends,
        ];
    }

    public function openWithFriend(int $hostUserId, int $friendUserId): WebcamMatch
    {
        if ($hostUserId === $friendUserId) {
            throw new DomainException('Wybierz innego gracza.', DomainExceptionHttp::UNPROCESSABLE);
        }

        if (! $this->friendships->areFriends($hostUserId, $friendUserId)) {
            throw new DomainException('Możesz zaprosić tylko znajomego.', DomainExceptionHttp::FORBIDDEN);
        }

        return DB::transaction(function () use ($hostUserId, $friendUserId) {
            $existing = $this->matches->unfinishedBetween($hostUserId, $friendUserId);
            if ($existing !== null) {
                return $existing;
            }

            return $this->matches->create($hostUserId, $friendUserId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function stateFor(int $userId, int $matchId): array
    {
        return $this->present($this->matchFor($userId, $matchId));
    }

    /**
     * @return array<string, mixed>
     */
    public function markReady(int $userId, int $matchId): array
    {
        $state = DB::transaction(function () use ($userId, $matchId) {
            $match = $this->lockedFor($userId, $matchId);

            if ($match->status === WebcamMatchStatus::Finished) {
                throw new DomainException('Mecz jest zakończony.');
            }

            if ((int) $match->host_user_id === $userId) {
                $match->host_ready_at ??= now();
            } else {
                $match->guest_ready_at ??= now();
            }

            if (
                $match->status === WebcamMatchStatus::Lobby
                && $match->host_ready_at !== null
                && $match->guest_ready_at !== null
            ) {
                $match->status = WebcamMatchStatus::Live;
                $match->started_at ??= now();
                $match->leg_number = 1;
                $match->current_turn_user_id = (int) $match->host_user_id;
                $match->host_legs_won = 0;
                $match->guest_legs_won = 0;
            }

            $this->matches->save($match);

            return $this->present($match);
        });

        $this->publishState($matchId, $state);

        return $state;
    }

    /**
     * @param  array{score: int, remaining_before: int, remaining_after: int, darts_in_visit: int, bust: bool, closed_leg: bool}  $input
     * @return array<string, mixed>
     */
    public function recordVisit(int $userId, int $matchId, array $input): array
    {
        $state = DB::transaction(function () use ($userId, $matchId, $input) {
            $match = $this->lockedFor($userId, $matchId);
            $this->assertCanThrow($match, $userId);

            $bust = (bool) $input['bust'];
            $closedLeg = (bool) $input['closed_leg'];
            $darts = VisitRecorder::normalizeDartsInVisit($bust, (int) $input['darts_in_visit']);
            $score = VisitRecorder::normalizeScore((int) $input['score'], $bust);
            $remainingAfter = (int) $input['remaining_after'];

            if (! $bust && ! $closedLeg && $darts < 3) {
                throw new DomainException('Zwykła wizyta musi mieć 3 lotki.');
            }

            if (! $bust && ! $closedLeg && $remainingAfter === 1) {
                throw new DomainException('Zostawienie 1 punktu to bust.');
            }

            $serverRemaining = $this->remainingFor($match, $userId, (int) $match->leg_number);
            VisitRecorder::assertRemainingBeforeMatchesServer((int) $input['remaining_before'], $serverRemaining);
            VisitRecorder::validate(
                remainingBefore: $serverRemaining,
                score: $score,
                remainingAfter: $remainingAfter,
                dartsInVisit: $darts,
                closedLeg: $closedLeg,
                bust: $bust,
                startingScore: self::STARTING_SCORE,
            );

            $visitNumber = $match->visits->where('leg_number', $match->leg_number)->count() + 1;
            $this->matches->addVisit($match, [
                'user_id' => $userId,
                'leg_number' => $match->leg_number,
                'visit_number' => $visitNumber,
                'score' => $score,
                'remaining_before' => $serverRemaining,
                'remaining_after' => $remainingAfter,
                'darts_in_visit' => $darts,
                'bust' => $bust,
                'closed_leg' => $closedLeg,
            ]);

            $match->unsetRelation('visits');
            $match->load('visits');
            $this->syncScore($match);
            $this->matches->save($match);

            return $this->present($match);
        });

        $this->publishState($matchId, $state);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function undoLastVisit(int $userId, int $matchId): array
    {
        $state = DB::transaction(function () use ($userId, $matchId) {
            $match = $this->lockedFor($userId, $matchId);

            if ($match->status === WebcamMatchStatus::Lobby) {
                throw new DomainException('Mecz jeszcze się nie zaczął.');
            }

            $last = $match->visits->sortBy('id')->last();
            if ($last === null || (int) $last->user_id !== $userId) {
                throw new DomainException('Możesz cofnąć tylko swoją ostatnią wizytę.');
            }

            $this->matches->deleteVisit($last);
            $match->unsetRelation('visits');
            $match->load('visits');
            $this->syncScore($match);
            $this->matches->save($match);

            return $this->present($match);
        });

        $this->publishState($matchId, $state);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signal(int $userId, int $matchId, string $kind, array $payload): void
    {
        $this->matchFor($userId, $matchId);

        try {
            broadcast(new WebcamMatchSignal($matchId, $userId, $kind, $payload));
        } catch (\Throwable $e) {
            report($e);
            throw new DomainException('Nie udało się wysłać sygnału obrazu. Sprawdź, czy serwer na żywo działa.');
        }
    }

    private function matchFor(int $userId, int $matchId): WebcamMatch
    {
        $match = $this->matches->findWithPlayers($matchId);
        if ($match === null || ! $match->hasParticipant($userId)) {
            throw (new ModelNotFoundException)->setModel(WebcamMatch::class, [$matchId]);
        }

        return $match;
    }

    private function lockedFor(int $userId, int $matchId): WebcamMatch
    {
        $match = $this->matches->lock($matchId);
        if (! $match->hasParticipant($userId)) {
            throw (new ModelNotFoundException)->setModel(WebcamMatch::class, [$matchId]);
        }

        return $match;
    }

    private function assertCanThrow(WebcamMatch $match, int $userId): void
    {
        if ($match->status === WebcamMatchStatus::Lobby) {
            throw new DomainException('Mecz zaczyna się, gdy oboje będziecie gotowi.');
        }

        if ($match->status === WebcamMatchStatus::Finished) {
            throw new DomainException('Mecz jest zakończony.');
        }

        if ((int) $match->current_turn_user_id !== $userId) {
            throw new DomainException('Teraz rzuca przeciwnik.');
        }
    }

    private function syncScore(WebcamMatch $match): void
    {
        $playerIds = [(int) $match->host_user_id, (int) $match->guest_user_id];
        $rows = $this->visitRows($match);
        $legs = VisitRecorder::countLegsWon($rows, $playerIds);
        $hostLegs = $legs[$playerIds[0]] ?? 0;
        $guestLegs = $legs[$playerIds[1]] ?? 0;
        $match->host_legs_won = $hostLegs;
        $match->guest_legs_won = $guestLegs;

        if ($hostLegs >= self::LEGS_TO_WIN || $guestLegs >= self::LEGS_TO_WIN) {
            $match->status = WebcamMatchStatus::Finished;
            $match->winner_user_id = $hostLegs >= self::LEGS_TO_WIN ? $playerIds[0] : $playerIds[1];
            $match->finished_at ??= now();
            $match->current_turn_user_id = null;
            $match->leg_number = (int) ($match->visits->max('leg_number') ?: 1);

            return;
        }

        $match->status = WebcamMatchStatus::Live;
        $match->winner_user_id = null;
        $match->finished_at = null;
        $openLeg = $this->openLegNumber($match);
        $match->leg_number = $openLeg;
        $opener = VisitRecorder::legOpenerIndexForLeg($rows, $playerIds, $openLeg, $openLeg);
        $legRows = array_values(array_filter(
            $rows,
            fn (array $row) => $row['legNumber'] === $openLeg,
        ));
        $turn = VisitRecorder::currentPlayerIndexFromVisits($legRows, $playerIds, $opener);
        $match->current_turn_user_id = $playerIds[$turn];
    }

    /**
     * VisitRecorder czyta wizyty jako tablicę z `playerId`, a tu rzucający jest użytkownikiem.
     *
     * @return list<array{playerId: int, score: int, remainingBefore: int, remainingAfter: int, dartsInVisit: int, closedLeg: bool, bust: bool, visitNumber: int, id: int, legNumber: int}>
     */
    private function visitRows(WebcamMatch $match): array
    {
        return $match->visits->map(fn ($visit) => [
            'playerId' => (int) $visit->user_id,
            'score' => (int) $visit->score,
            'remainingBefore' => (int) $visit->remaining_before,
            'remainingAfter' => (int) $visit->remaining_after,
            'dartsInVisit' => (int) $visit->darts_in_visit,
            'closedLeg' => (bool) $visit->closed_leg,
            'bust' => (bool) $visit->bust,
            'visitNumber' => (int) $visit->visit_number,
            'id' => (int) $visit->id,
            'legNumber' => (int) $visit->leg_number,
        ])->values()->all();
    }

    private function openLegNumber(WebcamMatch $match): int
    {
        if ($match->visits->isEmpty()) {
            return 1;
        }

        $max = (int) $match->visits->max('leg_number');
        $closed = $match->visits
            ->where('leg_number', $max)
            ->contains(fn ($visit) => (bool) $visit->closed_leg);

        return $closed ? $max + 1 : $max;
    }

    private function remainingFor(WebcamMatch $match, int $userId, int $legNumber): int
    {
        $last = $match->visits
            ->where('leg_number', $legNumber)
            ->where('user_id', $userId)
            ->sortBy('id')
            ->last();

        return $last === null ? self::STARTING_SCORE : (int) $last->remaining_after;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WebcamMatch $match): array
    {
        $match->loadMissing(['host.player', 'guest.player', 'visits']);
        $legNumber = (int) $match->leg_number;

        return [
            'id' => $match->id,
            'status' => $match->status->value,
            'legNumber' => $legNumber,
            'legsToWin' => self::LEGS_TO_WIN,
            'startingScore' => self::STARTING_SCORE,
            'currentTurnUserId' => $match->current_turn_user_id !== null ? (int) $match->current_turn_user_id : null,
            'winnerUserId' => $match->winner_user_id !== null ? (int) $match->winner_user_id : null,
            'lastVisitUserId' => ($last = $match->visits->sortBy('id')->last()) !== null ? (int) $last->user_id : null,
            'players' => [
                $this->playerPayload($match, (int) $match->host_user_id, $match->host, (int) $match->host_legs_won, $match->host_ready_at !== null, $legNumber),
                $this->playerPayload($match, (int) $match->guest_user_id, $match->guest, (int) $match->guest_legs_won, $match->guest_ready_at !== null, $legNumber),
            ],
            'visits' => $match->visits->map(fn ($visit) => [
                'id' => $visit->id,
                'userId' => (int) $visit->user_id,
                'legNumber' => (int) $visit->leg_number,
                'visitNumber' => (int) $visit->visit_number,
                'score' => (int) $visit->score,
                'remainingAfter' => (int) $visit->remaining_after,
                'dartsInVisit' => (int) $visit->darts_in_visit,
                'bust' => (bool) $visit->bust,
                'closedLeg' => (bool) $visit->closed_leg,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playerPayload(WebcamMatch $match, int $userId, ?User $user, int $legsWon, bool $ready, int $legNumber): array
    {
        return [
            'userId' => $userId,
            'name' => $user?->player?->name ?: 'Gracz',
            'legsWon' => $legsWon,
            'remaining' => $this->remainingFor($match, $userId, $legNumber),
            'ready' => $ready,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WebcamMatch $match, int $userId): array
    {
        $opponentId = $match->opponentUserId($userId);
        $opponent = (int) $match->host_user_id === $opponentId ? $match->host : $match->guest;

        return [
            'id' => $match->id,
            'status' => $match->status->value,
            'statusLabel' => match ($match->status) {
                WebcamMatchStatus::Lobby => 'Oczekuje',
                WebcamMatchStatus::Live => 'W trakcie',
                WebcamMatchStatus::Finished => 'Zakończony',
            },
            'opponentName' => $opponent?->player?->name ?: 'Gracz',
            'hostLegsWon' => (int) $match->host_legs_won,
            'guestLegsWon' => (int) $match->guest_legs_won,
            'youAreHost' => (int) $match->host_user_id === $userId,
            'href' => route('webcam.show', $match),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function publishState(int $matchId, array $state): void
    {
        try {
            broadcast(new WebcamMatchStateUpdated($matchId, $state));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
