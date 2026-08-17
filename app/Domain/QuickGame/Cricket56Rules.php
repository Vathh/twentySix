<?php

namespace App\Domain\QuickGame;

/**
 * Cricket 60 — 7 rund (15–20 + bull), 3 lotki na rundę, tylko trafienia w cel.
 *
 * 15–20: S=1, D=2, T=3. Bull: outer=1, inner=2.
 * Perfect: 60. Wygrywa najwyższy wynik po rundzie 7 (remis = runda od nowa).
 */
final class Cricket56Rules
{
    public const ROUND_COUNT = 7;

    public const LAST_ROUND_INDEX = 6;

    public const PERFECT_SCORE = 60;

    public const KIND_CONTINUE = 'continue';

    public const KIND_WIN = 'win';

    public const KIND_TIE_RESET = 'tie_reset';

    /**
     * @return list<int|string>
     */
    public static function targets(): array
    {
        return [15, 16, 17, 18, 19, 20, 'bull'];
    }

    public static function targetAt(int $index): int|string
    {
        $targets = self::targets();

        return $targets[$index] ?? 'bull';
    }

    public static function isBull(int $index): bool
    {
        return self::targetAt($index) === 'bull';
    }

    public static function maxMarkForRound(int $index): int
    {
        return self::isBull($index) ? 2 : 3;
    }

    public static function maxPointsForRound(int $index): int
    {
        return 3 * self::maxMarkForRound($index);
    }

    public static function targetLabel(int $index): string
    {
        $target = self::targetAt($index);

        return $target === 'bull' ? 'Bull' : (string) $target;
    }

    public static function clampMark(int $mark, int $roundIndex): int
    {
        return max(0, min(self::maxMarkForRound($roundIndex), $mark));
    }

    public static function clampPoints(int $points, int $roundIndex): int
    {
        return max(0, min(self::maxPointsForRound($roundIndex), $points));
    }

    public static function applyVisit(int $scoreBefore, int $points, int $roundIndex): int
    {
        return $scoreBefore + self::clampPoints($points, $roundIndex);
    }

    /**
     * @return array{score: int}
     */
    public static function emptyBoard(): array
    {
        return ['score' => 0];
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
            'currentRoundIndex' => 0,
            'dartsInVisit' => 0,
            'thrownThisRound' => [],
            'boards' => $boards,
            'dartLog' => [],
        ];
    }

    /**
     * @param  list<array{score?: int}>  $boards
     * @param  list<int>  $leftIndices
     * @return list<int>
     */
    public static function activeIndices(array $boards, array $leftIndices = []): array
    {
        $left = array_flip($leftIndices);
        $out = [];
        foreach ($boards as $i => $board) {
            if (isset($left[(int) $i])) {
                continue;
            }
            $out[] = (int) $i;
        }

        return $out;
    }

    /**
     * @param  list<array{score?: int}>  $boards
     * @param  array<int|string, bool>  $thrownThisRound
     * @param  list<int>  $leftIndices
     */
    public static function allActiveHaveThrown(array $boards, array $thrownThisRound, array $leftIndices = []): bool
    {
        $active = self::activeIndices($boards, $leftIndices);
        if ($active === []) {
            return true;
        }
        foreach ($active as $i) {
            if (empty($thrownThisRound[$i]) && empty($thrownThisRound[(string) $i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{score?: int}>  $boards
     * @param  list<int>  $leftIndices
     */
    public static function highestScoreIndex(array $boards, array $leftIndices = []): ?int
    {
        $active = self::activeIndices($boards, $leftIndices);
        if ($active === []) {
            return null;
        }

        $bestIdx = $active[0];
        $bestScore = (int) ($boards[$bestIdx]['score'] ?? 0);
        $tied = false;
        foreach ($active as $i) {
            if ($i === $bestIdx) {
                continue;
            }
            $score = (int) ($boards[$i]['score'] ?? 0);
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
     * @param  list<array{score?: int}>  $boards
     * @param  array<int|string, bool>  $thrownThisRound
     * @param  list<int>  $leftIndices
     * @return array{kind: string, winnerIndex?: int}
     */
    public static function resolveAfterCompletedVisit(
        array $boards,
        int $currentRoundIndex,
        array $thrownThisRound,
        array $leftIndices = [],
    ): array {
        if (! self::allActiveHaveThrown($boards, $thrownThisRound, $leftIndices)) {
            return ['kind' => self::KIND_CONTINUE];
        }

        if ($currentRoundIndex >= self::LAST_ROUND_INDEX) {
            $winner = self::highestScoreIndex($boards, $leftIndices);
            if ($winner === null) {
                return ['kind' => self::KIND_TIE_RESET];
            }

            return ['kind' => self::KIND_WIN, 'winnerIndex' => $winner];
        }

        return ['kind' => self::KIND_CONTINUE];
    }
}
