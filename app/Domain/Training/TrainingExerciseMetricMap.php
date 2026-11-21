<?php

namespace App\Domain\Training;

/**
 * Które ćwiczenie zasila które metryki celów/odznak (numer 13).
 */
final class TrainingExerciseMetricMap
{
    public const FEED_SCORING = 'scoring';

    public const FEED_DOUBLES = 'doubles';

    public const FEED_CHECKOUTS = 'checkouts';

    /**
     * @return array<string, list<string>>
     */
    public static function feeds(): array
    {
        return [
            'scoring_65' => [self::FEED_SCORING],
            // W mapie produktowej; metryki Scoring (średnia / ≥60) tylko z scoring_65.
            'scoring_under_pressure' => [self::FEED_SCORING],
            'doubles_clock' => [self::FEED_DOUBLES],
            'bobs_27_easy' => [self::FEED_DOUBLES],
            'bobs_27_hard' => [self::FEED_DOUBLES],
            'catch_20' => [self::FEED_DOUBLES, self::FEED_CHECKOUTS],
            'checkout_41' => [self::FEED_DOUBLES, self::FEED_CHECKOUTS],
            'checkout_bull_81' => [self::FEED_DOUBLES, self::FEED_CHECKOUTS],
        ];
    }

    /**
     * @return list<string>
     */
    public static function feedsFor(string $exerciseKey): array
    {
        return self::feeds()[$exerciseKey] ?? [];
    }

    public static function feedsScoringAverage(string $exerciseKey): bool
    {
        return $exerciseKey === 'scoring_65';
    }
}
