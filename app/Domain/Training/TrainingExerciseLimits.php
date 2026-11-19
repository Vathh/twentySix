<?php

namespace App\Domain\Training;

final class TrainingExerciseLimits
{
    public const MAX_DURATION_SECONDS = 14400;

    public const MAX_DART_COUNT = 500;

    /**
     * W planie wolno podać tylko zmienną tego ćwiczenia. Brak wartości zostawia domyślną.
     *
     * @param  array<mixed>  $item
     * @return array{durationSeconds: int|null, dartCount: int|null}
     */
    public static function forPlanSlot(string $exerciseKey, array $item): array
    {
        $defaults = TrainingExerciseCatalog::parameters($exerciseKey);
        if ($defaults === null) {
            throw new TrainingException(
                'Wskazane ćwiczenie nie istnieje.',
                'training_exercise_not_found',
            );
        }

        $timed = $defaults['durationSeconds'] !== null;
        $hasDuration = array_key_exists('durationSeconds', $item);
        $hasDarts = array_key_exists('dartCount', $item);
        if ($hasDuration && $hasDarts) {
            throw new TrainingException(
                'Ćwiczenie ma albo czas, albo liczbę lotek.',
                'training_exercise_wrong_limit',
            );
        }
        if ($timed && $hasDarts) {
            throw new TrainingException(
                'To ćwiczenie ustawia się czasem.',
                'training_exercise_wrong_limit',
            );
        }
        if (! $timed && $hasDuration) {
            throw new TrainingException(
                'To ćwiczenie ustawia się liczbą lotek.',
                'training_exercise_wrong_limit',
            );
        }

        if ($timed) {
            $duration = $hasDuration
                ? self::nullableInt($item['durationSeconds'], 'durationSeconds', self::MAX_DURATION_SECONDS)
                : $defaults['durationSeconds'];
            self::assertHasLimit($duration, null);

            return [
                'durationSeconds' => $duration,
                'dartCount' => null,
            ];
        }

        $dartCount = $hasDarts
            ? self::nullableInt($item['dartCount'], 'dartCount', self::MAX_DART_COUNT)
            : $defaults['dartCount'];
        self::assertHasLimit(null, $dartCount);

        return [
            'durationSeconds' => null,
            'dartCount' => $dartCount,
        ];
    }

    private static function assertHasLimit(?int $durationSeconds, ?int $dartCount): void
    {
        if ($durationSeconds === null && $dartCount === null) {
            throw new TrainingException(
                'Ćwiczenie wymaga czasu albo liczby lotek.',
                'training_exercise_needs_limit',
            );
        }
    }

    private static function nullableInt(mixed $value, string $field, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = null;
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $number = (int) $value;
        }

        if ($number === null || $number < 1 || $number > $max) {
            $label = $field === 'durationSeconds'
                ? 'Czas ćwiczenia mieści się między 1 sekundą a 4 godzinami.'
                : 'Liczba lotek mieści się między 1 a 500.';

            throw new TrainingException($label, 'training_definition_invalid');
        }

        return $number;
    }
}
