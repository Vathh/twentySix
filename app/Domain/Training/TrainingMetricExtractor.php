<?php

namespace App\Domain\Training;

/**
 * Wyciąga agregaty do celów/odznak z stats ćwiczenia (solo lub item planu).
 */
final class TrainingMetricExtractor
{
    /**
     * @param  array<string, mixed>|null  $stats
     * @return array{
     *   scoringTotal: int,
     *   scoringApproaches: int,
     *   scoringVisitsAtLeast60: int,
     *   doublesAttempts: int,
     *   doublesHits: int,
     *   checkoutsClosed: int,
     *   checkoutAttempts: int
     * }
     */
    public static function fromExercise(string $exerciseKey, ?array $stats): array
    {
        $empty = [
            'scoringTotal' => 0,
            'scoringApproaches' => 0,
            'scoringVisitsAtLeast60' => 0,
            'doublesAttempts' => 0,
            'doublesHits' => 0,
            'checkoutsClosed' => 0,
            'checkoutAttempts' => 0,
        ];
        $feeds = TrainingExerciseMetricMap::feedsFor($exerciseKey);
        if ($feeds === [] || $stats === null) {
            return $empty;
        }

        $out = $empty;
        if (in_array(TrainingExerciseMetricMap::FEED_SCORING, $feeds, true)
            && TrainingExerciseMetricMap::feedsScoringAverage($exerciseKey)
        ) {
            $out['scoringTotal'] = self::int($stats['total'] ?? 0);
            $out['scoringApproaches'] = self::int($stats['approaches'] ?? 0);
            $out['scoringVisitsAtLeast60'] = self::int($stats['visitsAtLeast60'] ?? 0);
        }

        if (in_array(TrainingExerciseMetricMap::FEED_DOUBLES, $feeds, true)) {
            [$attempts, $hits] = self::doublesFrom($exerciseKey, $stats);
            $out['doublesAttempts'] = $attempts;
            $out['doublesHits'] = $hits;
        }

        if (in_array(TrainingExerciseMetricMap::FEED_CHECKOUTS, $feeds, true)) {
            $out['checkoutsClosed'] = self::int($stats['checkoutsClosed'] ?? ($stats['closed'] ?? 0));
            $out['checkoutAttempts'] = self::int($stats['checkoutAttempts'] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{0: int, 1: int}
     */
    private static function doublesFrom(string $exerciseKey, array $stats): array
    {
        if ($exerciseKey === 'doubles_clock') {
            return [self::int($stats['attempts'] ?? 0), self::int($stats['hits'] ?? 0)];
        }

        if ($exerciseKey === 'catch_20' || $exerciseKey === 'checkout_41') {
            $aims = $stats['aims'] ?? null;
            if (is_array($aims) && $aims !== []) {
                return self::sumAims(
                    $aims,
                    static fn (string $aim): bool => preg_match('/^D([1-9]|1[0-9]|20)$/', $aim) === 1,
                );
            }

            // Legacy: starsze sesje miały stats.doubles z label.
            return self::sumDoublesList($stats['doubles'] ?? null);
        }

        if ($exerciseKey === 'checkout_bull_81') {
            return self::sumAims($stats['aims'] ?? null, static fn (string $aim): bool => $aim === 'Inner');
        }

        if ($exerciseKey === 'bobs_27_easy' || $exerciseKey === 'bobs_27_hard') {
            return self::sumAims(
                $stats['aims'] ?? null,
                static function (string $aim) use ($exerciseKey): bool {
                    if (preg_match('/^D([1-9]|1[0-9]|20)$/', $aim) === 1) {
                        return true;
                    }

                    return $exerciseKey === 'bobs_27_hard' && $aim === 'Inner';
                },
            );
        }

        return [0, 0];
    }

    /**
     * @param  mixed  $doubles
     * @return array{0: int, 1: int}
     */
    private static function sumDoublesList(mixed $doubles): array
    {
        if (! is_array($doubles) || ! array_is_list($doubles)) {
            return [0, 0];
        }
        $attempts = 0;
        $hits = 0;
        foreach ($doubles as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attempts += self::int($row['attempts'] ?? 0);
            $hits += self::int($row['hits'] ?? 0);
        }

        return [$attempts, $hits];
    }

    /**
     * @param  mixed  $aims
     * @param  callable(string): bool  $accept
     * @return array{0: int, 1: int}
     */
    private static function sumAims(mixed $aims, callable $accept): array
    {
        if (! is_array($aims) || ! array_is_list($aims)) {
            return [0, 0];
        }
        $attempts = 0;
        $hits = 0;
        foreach ($aims as $row) {
            if (! is_array($row)) {
                continue;
            }
            $aim = $row['aim'] ?? null;
            if (! is_string($aim) || ! $accept($aim)) {
                continue;
            }
            $attempts += self::int($row['attempts'] ?? 0);
            $hits += self::int($row['hits'] ?? 0);
        }

        return [$attempts, $hits];
    }

    private static function int(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }
        if (is_float($value) && $value >= 0) {
            return (int) $value;
        }

        return 0;
    }
}
