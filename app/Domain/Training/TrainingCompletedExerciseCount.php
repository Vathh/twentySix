<?php

namespace App\Domain\Training;

/**
 * Licznik ukończonych ćwiczeń (Analiza + globalny licznik admina).
 * Przerwy i pominięcia = 0. Wolna gra nie wchodzi (nie ma w training_sessions).
 */
final class TrainingCompletedExerciseCount
{
    /**
     * @param  array<string, mixed>|null  $stats
     */
    public static function forKind(TrainingKind $kind, ?array $stats): int
    {
        return match ($kind) {
            TrainingKind::Exercise => 1,
            TrainingKind::Plan, TrainingKind::Event => self::completedPlanExercises($stats),
            TrainingKind::Challenge => self::challengeStages($stats),
        };
    }

    /**
     * @param  array<string, mixed>|null  $stats
     */
    private static function completedPlanExercises(?array $stats): int
    {
        $items = is_array($stats) ? ($stats['items'] ?? null) : null;
        if (! is_array($items)) {
            return 0;
        }

        $count = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['kind'] ?? null) !== 'exercise') {
                continue;
            }
            if (($item['status'] ?? null) !== 'completed') {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>|null  $stats
     */
    private static function challengeStages(?array $stats): int
    {
        $stages = is_array($stats) ? ($stats['stages'] ?? null) : null;
        if (! is_array($stages) || $stages === []) {
            return 1;
        }

        return count($stages);
    }
}
