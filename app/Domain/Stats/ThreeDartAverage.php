<?php

namespace App\Domain\Stats;

/**
 * Średnia trzech lotek: (punkty bez bustu / wszystkie lotki) × 3.
 */
final class ThreeDartAverage
{
    public static function fromTotals(int $points, int $darts): ?float
    {
        if ($darts <= 0) {
            return null;
        }

        return round(($points / $darts) * 3, 2);
    }
}
