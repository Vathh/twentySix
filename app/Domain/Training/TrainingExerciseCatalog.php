<?php

namespace App\Domain\Training;

/**
 * Ta lista jest katalogiem ćwiczeń. Zasady są stałe.
 * Czas albo liczba lotek to domyślna zmienna ćwiczenia.
 * W planie administrator może zmienić tylko tę jedną zmienną.
 */
final class TrainingExerciseCatalog
{
    /**
     * @return array<string, array{durationSeconds: int|null, dartCount: int|null}>
     */
    public static function all(): array
    {
        return [
            'loose_throws' => ['durationSeconds' => 180, 'dartCount' => null],
            'nine_darts' => ['durationSeconds' => null, 'dartCount' => 36],
            'clock_1_20_bull' => ['durationSeconds' => null, 'dartCount' => 21],
            'clock_sdt' => ['durationSeconds' => 300, 'dartCount' => null],
            'bobs_27_easy' => ['durationSeconds' => 300, 'dartCount' => null],
            'bobs_27_hard' => ['durationSeconds' => 300, 'dartCount' => null],
            'catch_20' => ['durationSeconds' => 540, 'dartCount' => null],
            'doubles_clock' => ['durationSeconds' => null, 'dartCount' => 21],
            'scoring_under_pressure' => ['durationSeconds' => 300, 'dartCount' => null],
            'scoring_65' => ['durationSeconds' => 300, 'dartCount' => null],
            'singles_under_pressure' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_41' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_bull_81' => ['durationSeconds' => 480, 'dartCount' => null],
            'bull_counter' => ['durationSeconds' => 180, 'dartCount' => null],
            'shanghai_10_15' => ['durationSeconds' => null, 'dartCount' => 18],
            'doubles_54' => ['durationSeconds' => null, 'dartCount' => 21],
            'shanghai_16_20' => ['durationSeconds' => null, 'dartCount' => 15],
        ];
    }

    /**
     * @return array{durationSeconds: int|null, dartCount: int|null}|null
     */
    public static function parameters(string $exerciseKey): ?array
    {
        return self::all()[$exerciseKey] ?? null;
    }

    public static function label(string $exerciseKey): string
    {
        return self::labels()[$exerciseKey] ?? $exerciseKey;
    }

    public static function usesDuration(string $exerciseKey): bool
    {
        $parameters = self::parameters($exerciseKey);

        return $parameters !== null && $parameters['durationSeconds'] !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'loose_throws' => 'Luźne rzuty',
            'nine_darts' => '9/18 lotek',
            'clock_1_20_bull' => 'Zegar',
            'clock_sdt' => 'Zegar sektory',
            'bobs_27_easy' => "Bob's 27 easy",
            'bobs_27_hard' => "Bob's 27 hard",
            'catch_20' => 'Catch20',
            'doubles_clock' => 'Zegar doubli',
            'scoring_under_pressure' => 'Scoring z presją',
            'scoring_65' => 'Scoring 65',
            'singles_under_pressure' => 'Singielki z presją',
            'checkout_41' => 'Checkout 41+',
            'checkout_bull_81' => 'Checkout Bull 81+',
            'bull_counter' => 'Walka o licznik',
            'shanghai_10_15' => 'Shanghai 10–15',
            'doubles_54' => 'Doubles',
            'shanghai_16_20' => 'Shanghai 16–20',
        ];
    }
}
