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
            'scoring_20_19_18' => ['durationSeconds' => 540, 'dartCount' => null],
            'eighteen_darts' => ['durationSeconds' => null, 'dartCount' => 18],
            'doubles_clock' => ['durationSeconds' => null, 'dartCount' => 21],
            'scoring_under_pressure' => ['durationSeconds' => 900, 'dartCount' => null],
            'singles_under_pressure' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_41' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_bull_81' => ['durationSeconds' => 480, 'dartCount' => null],
            'bull_counter' => ['durationSeconds' => 180, 'dartCount' => null],
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
            'nine_darts' => '9 lotek',
            'clock_1_20_bull' => 'Zegar szybki',
            'clock_sdt' => 'Zegar sektory',
            'bobs_27_easy' => "Bob's 27 easy",
            'bobs_27_hard' => "Bob's 27 hard",
            'catch_20' => 'Catch20',
            'scoring_20_19_18' => 'Scoring 20/19/18',
            'eighteen_darts' => '18 lotek',
            'doubles_clock' => 'Zegar doubli',
            'scoring_under_pressure' => 'Scoring z presją',
            'singles_under_pressure' => 'Singielki z presją',
            'checkout_41' => 'Checkout 41+',
            'checkout_bull_81' => 'Checkout Bull 81+',
            'bull_counter' => 'Walka o licznik',
        ];
    }
}
