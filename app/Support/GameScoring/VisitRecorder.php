<?php

namespace App\Support\GameScoring;

use DomainException;
use Illuminate\Support\Collection;

/**
 * Wspólna logika wizyt: bust, remaining, dartsInVisit, closedLeg, kolejka tur.
 * Używana przez H2H (game_visits) i FFA (quick_game_ffa_visits).
 */
final class VisitRecorder
{
    public static function isVisitComplete(bool $bust, bool $closedLeg, int $dartsInVisit): bool
    {
        return $bust || $closedLeg || $dartsInVisit >= 3;
    }

    public static function normalizeScore(int $visitScore, bool $bust): int
    {
        return $bust ? 0 : $visitScore;
    }

    public static function computeRemainingAfter(
        int $remainingBefore,
        int $score,
        bool $bust,
        bool $closedLeg,
    ): int {
        if ($bust) {
            return $remainingBefore;
        }

        if ($closedLeg) {
            return 0;
        }

        return max(0, $remainingBefore - $score);
    }

    /**
     * @throws DomainException
     */
    public static function validate(
        int $remainingBefore,
        int $score,
        int $remainingAfter,
        int $dartsInVisit,
        bool $closedLeg,
        bool $bust,
        int $startingScore = 501,
    ): void {
        if ($dartsInVisit < 1 || $dartsInVisit > 3) {
            throw new DomainException('Liczba lotek w wizycie musi być od 1 do 3.');
        }

        if ($remainingBefore < 0 || $remainingBefore > $startingScore) {
            throw new DomainException('Nieprawidłowy wynik przed wizytą.');
        }

        if ($bust) {
            if ($score !== 0) {
                throw new DomainException('Przy bust suma wizyty musi być 0.');
            }
            if ($remainingAfter !== $remainingBefore) {
                throw new DomainException('Przy bust wynik po wizycie musi być taki sam jak przed.');
            }
            if ($closedLeg) {
                throw new DomainException('Bust nie może zamykać lega.');
            }

            return;
        }

        if ($score < 0 || $score > 180) {
            throw new DomainException('Suma wizyty musi być od 0 do 180.');
        }

        if ($score > $remainingBefore) {
            throw new DomainException('Wynik wizyty przekracza pozostały wynik.');
        }

        $expectedAfter = self::computeRemainingAfter($remainingBefore, $score, false, $closedLeg);
        if ($remainingAfter !== $expectedAfter) {
            throw new DomainException('Nieprawidłowy wynik po wizycie.');
        }

        if ($closedLeg) {
            if ($score !== $remainingBefore) {
                throw new DomainException('Checkout wymaga trafienia dokładnie pozostałego wyniku.');
            }
            if ($remainingAfter !== 0) {
                throw new DomainException('Po zamknięciu lega wynik musi być 0.');
            }
        }
    }

    /**
     * @throws DomainException
     */
    public static function validateDto(object $dto, int $startingScore = 501): void
    {
        self::validate(
            remainingBefore: (int) $dto->remainingBefore,
            score: (int) $dto->score,
            remainingAfter: (int) $dto->remainingAfter,
            dartsInVisit: (int) $dto->dartsInVisit,
            closedLeg: (bool) $dto->closedLeg,
            bust: (bool) $dto->bust,
            startingScore: $startingScore,
        );
    }

    /**
     * Indeks gracza, który ma rzucać po ostatniej wizycie w legu.
     *
     * @param  iterable<int, array<string, mixed>|object>  $visits
     * @param  array<int, int>  $playerIds
     */
    public static function currentPlayerIndexFromVisits(
        iterable $visits,
        array $playerIds,
        ?int $legOpenerIndex = null,
    ): int {
        if ($playerIds === []) {
            return 0;
        }

        $n = count($playerIds);
        $visitList = self::collectVisits($visits);

        if ($visitList->isEmpty()) {
            return $legOpenerIndex ?? 0;
        }

        $last = self::normalizeVisit($visitList->last());
        $lastIdx = array_search($last['playerId'], $playerIds, true);

        if ($lastIdx === false) {
            return $legOpenerIndex ?? 0;
        }

        if (! self::isVisitComplete($last['bust'], $last['closedLeg'], $last['dartsInVisit'])) {
            return (int) $lastIdx;
        }

        if ($last['bust']) {
            return (int) $lastIdx;
        }

        return ((int) $lastIdx + 1) % $n;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $legVisits
     */
    public static function remainingFromLegVisits(iterable $legVisits, int $startingScore): int
    {
        $visitList = self::collectVisits($legVisits);

        if ($visitList->isEmpty()) {
            return $startingScore;
        }

        $last = self::normalizeVisit($visitList->sortBy([
            ['visitNumber', 'asc'],
            ['id', 'asc'],
        ])->last());

        return $last['remainingAfter'];
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $legVisits
     */
    public static function legWinnerPlayerId(iterable $legVisits): ?int
    {
        $visitList = self::collectVisits($legVisits);

        $winner = $visitList
            ->filter(fn (array $v) => $v['closedLeg'] && ! $v['bust'] && $v['remainingAfter'] === 0)
            ->sortBy([
                ['visitNumber', 'desc'],
                ['id', 'desc'],
            ])
            ->first();

        return $winner ? $winner['playerId'] : null;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $allVisits
     * @param  array<int, int>  $playerIds
     * @return array<int, int>
     */
    public static function countLegsWon(iterable $allVisits, array $playerIds): array
    {
        $legsWon = array_fill_keys($playerIds, 0);
        $visitList = self::collectVisits($allVisits);

        foreach ($visitList->groupBy('legKey') as $legVisits) {
            $winnerId = self::legWinnerPlayerId($legVisits);
            if ($winnerId !== null && isset($legsWon[$winnerId])) {
                $legsWon[$winnerId]++;
            }
        }

        return $legsWon;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $visits
     * @return Collection<int, array{playerId: int, score: int, remainingBefore: int, remainingAfter: int, dartsInVisit: int, closedLeg: bool, bust: bool, visitNumber: int, id: int, legKey: int|string}>
     */
    private static function collectVisits(iterable $visits): Collection
    {
        $items = $visits instanceof Collection ? $visits->all() : iterator_to_array($visits);

        return collect($items)->map(fn ($visit) => self::normalizeVisit($visit));
    }

    /**
     * @return array{playerId: int, score: int, remainingBefore: int, remainingAfter: int, dartsInVisit: int, closedLeg: bool, bust: bool, visitNumber: int, id: int, legKey: int|string}
     */
    private static function normalizeVisit(array|object $visit): array
    {
        if (is_array($visit)) {
            $legKey = $visit['legNumber'] ?? $visit['gameLegId'] ?? $visit['legKey'] ?? 0;

            return [
                'playerId' => (int) ($visit['playerId'] ?? 0),
                'score' => (int) ($visit['score'] ?? 0),
                'remainingBefore' => (int) ($visit['remainingBefore'] ?? 0),
                'remainingAfter' => (int) ($visit['remainingAfter'] ?? 0),
                'dartsInVisit' => (int) ($visit['dartsInVisit'] ?? 3),
                'closedLeg' => (bool) ($visit['closedLeg'] ?? false),
                'bust' => (bool) ($visit['bust'] ?? false),
                'visitNumber' => (int) ($visit['visitNumber'] ?? 0),
                'id' => (int) ($visit['id'] ?? 0),
                'legKey' => $legKey,
            ];
        }

        $legKey = $visit->leg_number ?? $visit->game_leg_id ?? 0;

        return [
            'playerId' => (int) $visit->player_id,
            'score' => (int) $visit->score,
            'remainingBefore' => (int) $visit->remaining_before,
            'remainingAfter' => (int) $visit->remaining_after,
            'dartsInVisit' => (int) $visit->darts_in_visit,
            'closedLeg' => (bool) $visit->closed_leg,
            'bust' => (bool) $visit->bust,
            'visitNumber' => (int) $visit->visit_number,
            'id' => (int) $visit->id,
            'legKey' => $legKey,
        ];
    }
}
