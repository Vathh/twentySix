<?php

namespace App\Domain\QuickGame;

/**
 * Bob's 27 — trening dubli (D1–D20 + inner bull).
 *
 * Trafienie dodaje wartość dubla za każdą lotkę; trzy pudła odejmują jedną wartość.
 * Hard: wynik ≤ 0 eliminuje gracza. Easy: gra trwa przy ujemnym wyniku.
 * Bull: tylko inner (50).
 */
final class Bob27Rules
{
    public const STARTING_SCORE = 27;

    public const MODE_EASY = 'easy';

    public const MODE_HARD = 'hard';

    public const TARGET_COUNT = 21;

    public const LAST_TARGET_INDEX = 20;

    public const BULL_VALUE = 50;

    public const KIND_CONTINUE = 'continue';

    public const KIND_WIN = 'win';

    public const KIND_TIE_RESET = 'tie_reset';

    public const KIND_BUST = 'bust';

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

    public static function targetValue(int $index): int
    {
        $target = self::targetAt($index);

        return $target === 'bull' ? self::BULL_VALUE : ((int) $target) * 2;
    }

    public static function targetLabel(int $index): string
    {
        $target = self::targetAt($index);

        return $target === 'bull' ? 'Bull' : 'D'.$target;
    }

    public static function normalizeMode(string $mode): string
    {
        return strtolower($mode) === self::MODE_EASY ? self::MODE_EASY : self::MODE_HARD;
    }

    public static function applyVisit(int $scoreBefore, int $hits, int $targetIndex): int
    {
        $value = self::targetValue($targetIndex);
        $hits = max(0, min(3, $hits));

        if ($hits === 0) {
            return $scoreBefore - $value;
        }

        return $scoreBefore + ($hits * $value);
    }

    public static function shouldEliminate(int $scoreAfter, string $mode): bool
    {
        return self::normalizeMode($mode) === self::MODE_HARD && $scoreAfter <= 0;
    }

    /**
     * Perfect game: start 27 + 3×(D1…D20) + 3×50.
     */
    public static function perfectScore(): int
    {
        $sum = self::STARTING_SCORE;
        for ($i = 0; $i < self::TARGET_COUNT; $i++) {
            $sum += 3 * self::targetValue($i);
        }

        return $sum;
    }

    /**
     * @return array{score: int, eliminated: bool}
     */
    public static function emptyBoard(): array
    {
        return [
            'score' => self::STARTING_SCORE,
            'eliminated' => false,
        ];
    }

    /**
     * @param  list<int>  $playerIds
     * @return array<string, mixed>
     */
    public static function initialState(array $playerIds, string $mode = self::MODE_HARD): array
    {
        $boards = [];
        foreach ($playerIds as $pid) {
            $boards[(string) $pid] = self::emptyBoard();
        }

        return [
            'mode' => self::normalizeMode($mode),
            'currentTargetIndex' => 0,
            'dartsInVisit' => 0,
            'hitsInVisit' => 0,
            'thrownThisTarget' => [],
            'boards' => $boards,
            'dartLog' => [],
        ];
    }

    /**
     * Indeksy graczy nadal w grze (nie wyeliminowani, nie opuścili).
     *
     * @param  list<array{score: int, eliminated: bool}>  $boards
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
            if (! empty($board['eliminated'])) {
                continue;
            }
            $out[] = (int) $i;
        }

        return $out;
    }

    /**
     * Czy wszyscy aktywni rzucili już w bieżący cel.
     *
     * @param  list<array{score: int, eliminated: bool}>  $boards
     * @param  array<int|string, bool>  $thrownThisTarget  klucz = indeks gracza
     * @param  list<int>  $leftIndices
     */
    public static function allActiveHaveThrown(array $boards, array $thrownThisTarget, array $leftIndices = []): bool
    {
        $active = self::activeIndices($boards, $leftIndices);
        if ($active === []) {
            return true;
        }
        foreach ($active as $i) {
            if (empty($thrownThisTarget[$i]) && empty($thrownThisTarget[(string) $i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Najwyższy wynik wśród aktywnych — null przy remisie lub braku aktywnych.
     *
     * @param  list<array{score: int, eliminated: bool}>  $boards
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
     * Wynik po domkniętej wizycie (3 lotki).
     *
     * @param  list<array{score: int, eliminated: bool}>  $boards
     * @param  array<int|string, bool>  $thrownThisTarget
     * @param  list<int>  $leftIndices
     * @return array{kind: string, winnerIndex?: int}
     */
    public static function resolveAfterCompletedVisit(
        array $boards,
        string $mode,
        int $currentTargetIndex,
        array $thrownThisTarget,
        array $leftIndices = [],
    ): array {
        $mode = self::normalizeMode($mode);
        $active = self::activeIndices($boards, $leftIndices);

        if ($mode === self::MODE_HARD) {
            if (count($active) === 1) {
                return ['kind' => self::KIND_WIN, 'winnerIndex' => $active[0]];
            }
            if ($active === []) {
                return ['kind' => self::KIND_BUST];
            }
        }

        if (! self::allActiveHaveThrown($boards, $thrownThisTarget, $leftIndices)) {
            return ['kind' => self::KIND_CONTINUE];
        }

        if ($currentTargetIndex >= self::LAST_TARGET_INDEX) {
            $winner = self::highestScoreIndex($boards, $leftIndices);
            if ($winner === null) {
                return ['kind' => self::KIND_TIE_RESET];
            }

            return ['kind' => self::KIND_WIN, 'winnerIndex' => $winner];
        }

        return ['kind' => self::KIND_CONTINUE];
    }
}
