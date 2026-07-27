<?php

namespace App\Support\QuickGameFfa;

/**
 * Standard / scoring cricket — reguły zgodne z mobile helpers/cricket/cricketRules.js
 */
final class CricketRules
{
    /** @var list<int|string> */
    public const SEGMENTS = [20, 19, 18, 17, 16, 15, 'bull'];

    public static function emptyHits(): array
    {
        return [
            '20' => 0,
            '19' => 0,
            '18' => 0,
            '17' => 0,
            '16' => 0,
            '15' => 0,
            'bull' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $hits
     * @return array<string, int>
     */
    public static function normalizeHits(array $hits): array
    {
        $out = self::emptyHits();
        foreach ($out as $key => $_) {
            if (array_key_exists($key, $hits)) {
                $out[$key] = (int) $hits[$key];
            } elseif ($key !== 'bull' && array_key_exists((int) $key, $hits)) {
                $out[$key] = (int) $hits[(int) $key];
            }
        }

        return $out;
    }

    public static function segmentKey(int|string $segment): string
    {
        return $segment === 'bull' || $segment === 25 ? 'bull' : (string) $segment;
    }

    public static function segmentPoints(int|string $segment): int
    {
        $key = self::segmentKey($segment);

        return $key === 'bull' ? 25 : (int) $key;
    }

    public static function isSegmentClosed(array $hits, int|string $segment): bool
    {
        $key = self::segmentKey($segment);

        return (int) ($hits[$key] ?? 0) >= 3;
    }

    public static function allSegmentsClosed(array $hits): bool
    {
        foreach (self::SEGMENTS as $seg) {
            if (! self::isSegmentClosed($hits, $seg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, int>>  $hitsByPlayerIndex
     */
    public static function canScoreMark(array $hitsByPlayerIndex, int $playerIndex, int|string $segment): bool
    {
        if (! self::isSegmentClosed($hitsByPlayerIndex[$playerIndex] ?? [], $segment)) {
            return false;
        }

        foreach ($hitsByPlayerIndex as $i => $hits) {
            if ((int) $i === $playerIndex) {
                continue;
            }
            if (! self::isSegmentClosed($hits, $segment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, int>>  $hitsByPlayerIndex
     * @return array{hits: array<string, int>, pointsScored: int}
     */
    public static function applyDart(array $hitsByPlayerIndex, int $playerIndex, int|string $segment, int $multiplier): array
    {
        $next = [];
        foreach ($hitsByPlayerIndex as $i => $hits) {
            $next[(int) $i] = self::normalizeHits(is_array($hits) ? $hits : []);
        }

        $key = self::segmentKey($segment);
        $marks = max(1, min(3, $multiplier));
        $value = self::segmentPoints($segment);
        $pointsScored = 0;

        for ($m = 0; $m < $marks; $m++) {
            $current = (int) ($next[$playerIndex][$key] ?? 0);
            if ($current < 3) {
                $next[$playerIndex][$key] = $current + 1;

                continue;
            }
            if (self::canScoreMark($next, $playerIndex, $key)) {
                $next[$playerIndex][$key] = $current + 1;
                $pointsScored += $value;
            }
        }

        return [
            'hits' => $next[$playerIndex],
            'pointsScored' => $pointsScored,
        ];
    }

    /**
     * @param  list<array{hits: array<string, int>, points: int}>  $boards
     */
    public static function findLegWinnerIndex(array $boards): ?int
    {
        foreach ($boards as $i => $board) {
            if (! self::allSegmentsClosed($board['hits'] ?? [])) {
                continue;
            }
            $pts = (int) ($board['points'] ?? 0);
            $leadsAll = true;
            foreach ($boards as $j => $other) {
                if ((int) $j === (int) $i) {
                    continue;
                }
                if ($pts <= (int) ($other['points'] ?? 0)) {
                    $leadsAll = false;
                    break;
                }
            }
            if ($leadsAll) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    public static function initialState(array $playerIds): array
    {
        $boards = [];
        foreach ($playerIds as $pid) {
            $boards[(string) $pid] = [
                'hits' => self::emptyHits(),
                'points' => 0,
            ];
        }

        return [
            'boards' => $boards,
            'dartsInVisit' => 0,
            'dartLog' => [],
        ];
    }

    public static function isValidSegment(int|string $segment): bool
    {
        $key = self::segmentKey($segment);
        if ($key === 'bull') {
            return true;
        }

        return in_array((int) $key, [15, 16, 17, 18, 19, 20], true);
    }
}
