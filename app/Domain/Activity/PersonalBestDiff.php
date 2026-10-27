<?php

namespace App\Domain\Activity;

/**
 * Poprawa życiówki. Pierwsza średnia meczu nie jest rekordem — każda pierwsza gra miałaby wpis.
 * Pierwszy finish od 100 i pierwsza wygrana lotka poniżej 20 już tak.
 */
final class PersonalBestDiff
{
    public const HF_MIN = 100;

    public const QF_UNDER = 20;

    /**
     * @return list<array{kind: string, value: float|int}>
     */
    public static function improved(
        ?float $previousAverage,
        ?float $average,
        ?int $previousHf,
        ?int $hf,
        ?int $previousQf,
        ?int $qf,
    ): array {
        $records = [];

        if ($previousAverage !== null && $average !== null && round($average, 2) > round($previousAverage, 2)) {
            $records[] = ['kind' => 'average', 'value' => round($average, 2)];
        }

        if ($hf !== null && $hf >= self::HF_MIN && ($previousHf === null || $hf > $previousHf)) {
            $records[] = ['kind' => 'hf', 'value' => $hf];
        }

        if ($qf !== null && $qf < self::QF_UNDER && ($previousQf === null || $qf < $previousQf)) {
            $records[] = ['kind' => 'qf', 'value' => $qf];
        }

        return $records;
    }
}
