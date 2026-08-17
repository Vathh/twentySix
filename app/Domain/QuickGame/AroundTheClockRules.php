<?php

namespace App\Domain\QuickGame;

/**
 * Around the Clock — wyścig 1 → 20 → bull (outer lub inner).
 *
 * Dowolny segment aktualnego numeru (S/D/T). Bez przeskoków.
 * Outer bull nie liczy się. Trafienie ostatniego celu zamyka lega od razu.
 */
final class AroundTheClockRules
{
    public const TARGET_COUNT = 21;

    public const LAST_TARGET_INDEX = 20;

    public const FINISHED_INDEX = 21;

    public const KIND_CONTINUE = 'continue';

    public const KIND_WIN = 'win';

    /**
     * @return list<int|string>
     */
    public static function targets(): array
    {
        $out = [];
        for ($n = 1; $n <= 20; $n++) {
            $out[] = $n;
        }
        $out[] = 'bull';

        return $out;
    }

    public static function targetAt(int $index): int|string
    {
        $targets = self::targets();

        return $targets[$index] ?? 'bull';
    }

    public static function targetLabel(int $index): string
    {
        if ($index >= self::TARGET_COUNT) {
            return '✓';
        }
        $target = self::targetAt($index);

        return $target === 'bull' ? 'Bull' : (string) $target;
    }

    public static function remaining(int $targetIndex): int
    {
        return max(0, self::TARGET_COUNT - max(0, $targetIndex));
    }

    public static function maxHits(int $targetIndex): int
    {
        return min(3, self::remaining($targetIndex));
    }

    public static function clampHits(int $hits, int $targetIndex): int
    {
        return max(0, min(self::maxHits($targetIndex), $hits));
    }

    /**
     * @return array{targetIndex: int, finished: bool}
     */
    public static function applyVisit(int $targetIndexBefore, int $hits): array
    {
        $before = max(0, min(self::TARGET_COUNT, $targetIndexBefore));
        if ($before >= self::TARGET_COUNT) {
            return [
                'targetIndex' => self::TARGET_COUNT,
                'finished' => true,
            ];
        }

        $hits = self::clampHits($hits, $before);
        $next = $before + $hits;
        $finished = $next >= self::TARGET_COUNT;

        return [
            'targetIndex' => $finished ? self::TARGET_COUNT : $next,
            'finished' => $finished,
        ];
    }

    /**
     * @return array{targetIndex: int, finished: bool}
     */
    public static function emptyBoard(): array
    {
        return [
            'targetIndex' => 0,
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
}
