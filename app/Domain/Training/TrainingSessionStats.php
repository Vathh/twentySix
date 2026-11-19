<?php

namespace App\Domain\Training;

/**
 * Na serwer idą statystyki ukończonej sesji, nie lista kolejnych lotek.
 */
final class TrainingSessionStats
{
    private const FORBIDDEN_KEYS = [
        'dart',
        'darts',
        'throw',
        'throws',
        'visit',
        'visits',
        'lotka',
        'lotki',
        'rzut',
        'rzuty',
        'shot',
        'shots',
        'remainingbefore',
        'remainingafter',
        'dartsinvisit',
        'closedleg',
        'clientvisitid',
    ];

    private const DART_SHAPE_KEYS = [
        'ring',
        'multiplier',
        'segment',
        'bed',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function assertAggregate(mixed $stats): array
    {
        if (! is_array($stats) || ($stats !== [] && array_is_list($stats))) {
            throw new TrainingException(
                'Statystyki muszą być obiektem.',
                'training_stats_invalid',
            );
        }

        $encoded = json_encode($stats);
        if ($encoded === false || strlen($encoded) > 32768) {
            throw new TrainingException(
                'Statystyki są zbyt duże.',
                'training_stats_invalid',
            );
        }

        self::assertNode($stats, 0);

        return $stats;
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function assertNode(array $node, int $depth): void
    {
        if ($depth > 6) {
            throw new TrainingException(
                'Statystyki są zbyt zagnieżdżone.',
                'training_stats_invalid',
            );
        }

        if (array_is_list($node) && count($node) > 32) {
            throw new TrainingException(
                'Na serwer nie zapisujemy listy lotek. Wyślij statystyki.',
                'training_dart_log_rejected',
            );
        }

        if (! array_is_list($node)) {
            self::assertObject($node);
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::assertNode($value, $depth + 1);

                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                throw new TrainingException(
                    'Statystyki muszą być obiektem.',
                    'training_stats_invalid',
                );
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function assertObject(array $node): void
    {
        $keys = [];
        foreach (array_keys($node) as $key) {
            if (! is_string($key)) {
                continue;
            }

            $normalized = strtolower($key);
            $keys[] = $normalized;
            if (in_array($normalized, self::FORBIDDEN_KEYS, true)) {
                throw new TrainingException(
                    'Na serwer nie zapisujemy listy lotek. Wyślij statystyki.',
                    'training_dart_log_rejected',
                );
            }
        }

        if (in_array('sector', $keys, true) && array_intersect($keys, self::DART_SHAPE_KEYS) !== []) {
            throw new TrainingException(
                'Na serwer nie zapisujemy listy lotek. Wyślij statystyki.',
                'training_dart_log_rejected',
            );
        }
    }
}
