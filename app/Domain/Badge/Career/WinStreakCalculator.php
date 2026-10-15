<?php

namespace App\Domain\Badge\Career;

/**
 * Rekord serii nie spada przez nową przegraną. Spada tylko wtedy, gdy cała historia
 * (po korekcie) daje niższe maksimum.
 */
final class WinStreakCalculator
{
    /**
     * @param  list<bool>  $wins  true = wygrana, w kolejności zakończenia meczów
     * @return array{best: int, current: int}
     */
    public static function fromWins(array $wins): array
    {
        $best = 0;
        $current = 0;

        foreach ($wins as $win) {
            $current = $win ? $current + 1 : 0;
            if ($current > $best) {
                $best = $current;
            }
        }

        return [
            'best' => $best,
            'current' => $current,
        ];
    }
}
