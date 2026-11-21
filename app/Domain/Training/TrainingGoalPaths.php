<?php

namespace App\Domain\Training;

/**
 * Progi celów. Naliczanie z ćwiczeń dochodzi w numerze 13 — tu definicja drabinek.
 */
final class TrainingGoalPaths
{
    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   unit: string,
     *   rule: string,
     *   thresholds: list<int|float>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'scoring',
                'name' => 'Scoring',
                'unit' => 'avg',
                'rule' => 'Średnia punktów na trzy rzuty.',
                'thresholds' => [30, 40, 50, 60, 65, 70, 73, 76, 79, 82, 85, 88, 91, 94, 97, 100],
            ],
            [
                'id' => 'doubles',
                'name' => 'Doubles',
                'unit' => 'percent',
                'rule' => 'Skuteczność D1–D20.',
                'thresholds' => [5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35],
            ],
            [
                'id' => 'checkouts',
                'name' => 'Checkouty',
                'unit' => 'percent',
                'rule' => 'Skuteczność checkoutów.',
                'thresholds' => [2, 4, 6, 8, 10, 12, 14, 16, 18, 20],
            ],
            [
                'id' => 'regularity',
                'name' => 'Regularność',
                'unit' => 'sessions',
                'rule' => 'Ukończone sesje w kroczących 30 dniach. Cały plan to jedna sesja.',
                'thresholds' => [5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35, 38, 41, 44, 47, 50],
            ],
            [
                'id' => 'challenges',
                'name' => 'Wyzwania',
                'unit' => 'score',
                'rule' => 'Najlepszy ukończony wynik 54-Dart Challenge.',
                'thresholds' => range(100, 3100, 100),
            ],
        ];
    }
}
