<?php

namespace App\Domain\Training;

/**
 * Wyniki slotów ukończonego planu. Bez listy lotek — tylko agregaty.
 */
final class TrainingPlanSessionItems
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function assert(mixed $items): array
    {
        if ($items === null) {
            return [];
        }
        if (! is_array($items) || ($items !== [] && ! array_is_list($items))) {
            throw new TrainingException(
                'Wyniki elementów planu muszą być listą.',
                'training_session_invalid',
            );
        }
        if (count($items) > 80) {
            throw new TrainingException(
                'Zbyt wiele elementów planu w sesji.',
                'training_session_invalid',
            );
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new TrainingException(
                    'Element wyniku planu musi być obiektem.',
                    'training_session_invalid',
                );
            }

            $extra = array_diff(array_map(strval(...), array_keys($item)), [
                'position',
                'kind',
                'exerciseKey',
                'status',
                'score',
                'scored',
                'aims',
                'stats',
            ]);
            if ($extra !== []) {
                throw new TrainingException(
                    'Pole „'.array_values($extra)[0].'” nie należy do wyniku elementu planu.',
                    'training_unexpected_field',
                );
            }

            $kind = (string) ($item['kind'] ?? '');
            if (! in_array($kind, ['exercise', 'break'], true)) {
                throw new TrainingException(
                    'Nieznany rodzaj elementu planu.',
                    'training_session_invalid',
                );
            }

            $status = (string) ($item['status'] ?? '');
            if (! in_array($status, ['completed', 'skipped'], true)) {
                throw new TrainingException(
                    'Status elementu planu to completed albo skipped.',
                    'training_session_invalid',
                );
            }

            $position = $item['position'] ?? null;
            if (! is_int($position) || $position < 1 || $position > 80) {
                throw new TrainingException(
                    'Pozycja elementu planu jest nieprawidłowa.',
                    'training_session_invalid',
                );
            }

            $row = [
                'position' => $position,
                'kind' => $kind,
                'status' => $status,
                'scored' => (bool) ($item['scored'] ?? false),
                'score' => self::score($item['score'] ?? null),
                'exerciseKey' => null,
                'aims' => [],
                'stats' => null,
            ];

            if ($kind === 'exercise') {
                $key = $item['exerciseKey'] ?? null;
                if (! is_string($key) || TrainingExerciseCatalog::parameters($key) === null) {
                    throw new TrainingException(
                        'Wskazane ćwiczenie nie istnieje.',
                        'training_exercise_not_found',
                    );
                }
                $row['exerciseKey'] = $key;
                $row['aims'] = self::aims($item['aims'] ?? []);
                if (array_key_exists('stats', $item) && $item['stats'] !== null) {
                    $row['stats'] = TrainingSessionStats::assertAggregate($item['stats']);
                }
            } elseif (
                ($item['exerciseKey'] ?? null) !== null
                || ($item['aims'] ?? null) !== null
                || ($item['stats'] ?? null) !== null
            ) {
                throw new TrainingException(
                    'Przerwa nie ma ćwiczenia ani celów.',
                    'training_session_invalid',
                );
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    private static function score(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new TrainingException(
            'Wynik elementu planu musi być liczbą całkowitą.',
            'training_session_invalid',
        );
    }

    /**
     * @return list<array{aim: string, attempts: int, hits: int}>
     */
    private static function aims(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || ($value !== [] && ! array_is_list($value))) {
            throw new TrainingException(
                'Cele elementu planu muszą być listą.',
                'training_session_invalid',
            );
        }
        if (count($value) > 64) {
            throw new TrainingException(
                'Zbyt wiele celów w elemencie planu.',
                'training_session_invalid',
            );
        }

        $aims = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new TrainingException(
                    'Cel elementu planu musi być obiektem.',
                    'training_session_invalid',
                );
            }
            $aim = $row['aim'] ?? null;
            $attempts = $row['attempts'] ?? null;
            $hits = $row['hits'] ?? null;
            if (! is_string($aim) || $aim === '' || mb_strlen($aim) > 32) {
                throw new TrainingException(
                    'Cel lotki jest nieprawidłowy.',
                    'training_session_invalid',
                );
            }
            if (! is_int($attempts) || $attempts < 0 || $attempts > 10000) {
                throw new TrainingException(
                    'Liczba prób celu jest nieprawidłowa.',
                    'training_session_invalid',
                );
            }
            if (! is_int($hits) || $hits < 0 || $hits > $attempts) {
                throw new TrainingException(
                    'Liczba trafień celu jest nieprawidłowa.',
                    'training_session_invalid',
                );
            }
            $aims[] = [
                'aim' => $aim,
                'attempts' => $attempts,
                'hits' => $hits,
            ];
        }

        return $aims;
    }
}
