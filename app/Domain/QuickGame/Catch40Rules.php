<?php

namespace App\Domain\QuickGame;

use DomainException;

/**
 * Catch 40 (John Part) — checkouty 61→100, max 6 lotek na out.
 *
 * 2 lotki = 3 pkt, 3 lotki = 2 pkt, 4–6 = 1 pkt, pudło = 0.
 * Wyjątek: 99 w 3 lotki = 3 pkt. Max 120.
 * Double out. Każdy gracz ma własny numer. Lega zamyka się po wszystkich 40 outach.
 */
final class Catch40Rules
{
    public const FIRST_OUT = 61;

    public const LAST_OUT = 100;

    public const OUT_COUNT = 40;

    public const MAX_DARTS_PER_OUT = 6;

    public const MAX_SCORE = 120;

    public const KIND_CONTINUE = 'continue';

    public const KIND_WIN = 'win';

    public const KIND_TIE_RESET = 'tie_reset';

    public static function pointsForCheckout(int $outNumber, int $dartsUsed): int
    {
        if ($dartsUsed < 2 || $dartsUsed > self::MAX_DARTS_PER_OUT) {
            return 0;
        }
        if ($outNumber === 99 && $dartsUsed === 3) {
            return 3;
        }
        if ($dartsUsed === 2) {
            return 3;
        }
        if ($dartsUsed === 3) {
            return 2;
        }

        return 1;
    }

    public static function nextOut(int $outNumber): ?int
    {
        if ($outNumber >= self::LAST_OUT) {
            return null;
        }

        return $outNumber + 1;
    }

    /**
     * @return array{outNumber: int, remaining: int, dartsUsed: int, catch40Score: int, finished: bool}
     */
    public static function emptyBoard(): array
    {
        return [
            'outNumber' => self::FIRST_OUT,
            'remaining' => self::FIRST_OUT,
            'dartsUsed' => 0,
            'catch40Score' => 0,
            'finished' => false,
        ];
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    public static function initialState(array $playerIds): array
    {
        $boards = [];
        foreach ($playerIds as $pid) {
            $boards[(string) $pid] = self::emptyBoard();
        }

        return [
            'boards' => $boards,
            'dartLog' => [],
        ];
    }

    /**
     * @param  array{outNumber?: int, remaining?: int, dartsUsed?: int, catch40Score?: int, finished?: bool}  $board
     * @return array{outNumber: int, remaining: int, dartsUsed: int, catch40Score: int, finished: bool}
     */
    public static function applyVisit(
        array $board,
        int $score,
        int $remainingAfter,
        int $dartsInVisit,
        bool $bust,
        bool $checkout,
    ): array {
        if (! empty($board['finished'])) {
            return self::normalizeBoard($board);
        }

        $outNumber = (int) ($board['outNumber'] ?? self::FIRST_OUT);
        $remainingBefore = (int) ($board['remaining'] ?? $outNumber);
        $dartsUsed = (int) ($board['dartsUsed'] ?? 0);
        $catch40Score = (int) ($board['catch40Score'] ?? 0);
        $dartsInVisit = $dartsInVisit > 0 ? min(3, $dartsInVisit) : 3;
        $totalDarts = $dartsUsed + $dartsInVisit;

        if ($totalDarts > self::MAX_DARTS_PER_OUT) {
            throw new DomainException('Na outa można zużyć maksymalnie 6 lotek.');
        }

        if ($checkout) {
            if ($bust) {
                throw new DomainException('Bust nie może zamykać outa.');
            }
            if ($remainingAfter !== 0) {
                throw new DomainException('Checkout wymaga residual 0.');
            }
            if ($totalDarts < 2) {
                throw new DomainException('Checkout 61–100 wymaga co najmniej 2 lotek.');
            }
            if ($outNumber === 99 && $totalDarts === 2) {
                throw new DomainException('99 nie da się zamknąć w 2 lotki.');
            }

            $catch40Score += self::pointsForCheckout($outNumber, $totalDarts);

            return self::advanceOut($outNumber, $catch40Score);
        }

        $remaining = $bust ? $remainingBefore : $remainingAfter;
        if (! $bust && $remaining !== ($remainingBefore - $score)) {
            throw new DomainException('Nieprawidłowy wynik po wizycie.');
        }

        if ($totalDarts >= self::MAX_DARTS_PER_OUT) {
            return self::advanceOut($outNumber, $catch40Score);
        }

        return [
            'outNumber' => $outNumber,
            'remaining' => $remaining,
            'dartsUsed' => $totalDarts,
            'catch40Score' => $catch40Score,
            'finished' => false,
        ];
    }

    /**
     * @param  list<array{catch40Score?: int, finished?: bool}>  $boards
     * @param  list<int>  $leftIndices
     * @return array{kind: string, winnerIndex?: int}
     */
    public static function resolveAfterVisit(array $boards, array $leftIndices = []): array
    {
        $active = self::activeIndices($boards, $leftIndices);
        foreach ($active as $i) {
            if (empty($boards[$i]['finished'])) {
                return ['kind' => self::KIND_CONTINUE];
            }
        }

        $winner = self::highestScoreIndex($boards, $leftIndices);
        if ($winner === null) {
            return ['kind' => self::KIND_TIE_RESET];
        }

        return ['kind' => self::KIND_WIN, 'winnerIndex' => $winner];
    }

    /**
     * @param  list<array{finished?: bool}>  $boards
     * @param  list<int>  $leftIndices
     * @return list<int>
     */
    public static function activeIndices(array $boards, array $leftIndices = []): array
    {
        $left = array_flip($leftIndices);
        $out = [];
        foreach ($boards as $i => $board) {
            if (isset($left[$i])) {
                continue;
            }
            $out[] = (int) $i;
        }

        return $out;
    }

    /**
     * @param  list<array{catch40Score?: int, finished?: bool}>  $boards
     * @param  list<int>  $leftIndices
     */
    public static function highestScoreIndex(array $boards, array $leftIndices = []): ?int
    {
        $active = self::activeIndices($boards, $leftIndices);
        if ($active === []) {
            return null;
        }

        $bestIdx = $active[0];
        $bestScore = (int) ($boards[$bestIdx]['catch40Score'] ?? 0);
        $tied = false;
        foreach ($active as $i) {
            if ($i === $bestIdx) {
                continue;
            }
            $score = (int) ($boards[$i]['catch40Score'] ?? 0);
            if ($score > $bestScore) {
                $bestIdx = $i;
                $bestScore = $score;
                $tied = false;
            } elseif ($score === $bestScore) {
                $tied = true;
            }
        }

        return $tied ? null : $bestIdx;
    }

    /**
     * @param  array{outNumber?: int, remaining?: int, dartsUsed?: int, catch40Score?: int, finished?: bool}  $board
     * @return array{outNumber: int, remaining: int, dartsUsed: int, catch40Score: int, finished: bool}
     */
    public static function normalizeBoard(array $board): array
    {
        $empty = self::emptyBoard();

        return [
            'outNumber' => (int) ($board['outNumber'] ?? $empty['outNumber']),
            'remaining' => (int) ($board['remaining'] ?? $empty['remaining']),
            'dartsUsed' => (int) ($board['dartsUsed'] ?? 0),
            'catch40Score' => (int) ($board['catch40Score'] ?? 0),
            'finished' => (bool) ($board['finished'] ?? false),
        ];
    }

    /**
     * @return array{outNumber: int, remaining: int, dartsUsed: int, catch40Score: int, finished: bool}
     */
    private static function advanceOut(int $outNumber, int $catch40Score): array
    {
        $next = self::nextOut($outNumber);
        if ($next === null) {
            return [
                'outNumber' => self::LAST_OUT,
                'remaining' => 0,
                'dartsUsed' => 0,
                'catch40Score' => $catch40Score,
                'finished' => true,
            ];
        }

        return [
            'outNumber' => $next,
            'remaining' => $next,
            'dartsUsed' => 0,
            'catch40Score' => $catch40Score,
            'finished' => false,
        ];
    }
}
