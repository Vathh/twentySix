<?php

namespace App\Domain\Training;

final class TrainingExerciseLimits
{
    public const MAX_DURATION_SECONDS = 14400;

    public const MAX_DART_COUNT = 500;

    /**
     * Dozwolone wartości silnika dla selecta w planie.
     * Etykiety UI (9/18) mapują się na łączną liczbę lotek schematu (36/72).
     *
     * @return list<int>|null null = dowolna liczba 1–500
     */
    public static function allowedDartCounts(string $exerciseKey): ?array
    {
        return match ($exerciseKey) {
            'nine_darts' => [36, 72],
            'clock_1_20_bull', 'doubles_clock', 'doubles_54' => [21],
            'shanghai_10_15' => [18],
            'shanghai_16_20' => [15],
            default => null,
        };
    }

    /**
     * Opcje selecta: value = silnik, label = to, co widzi admin.
     *
     * @return list<array{value: int, label: string}>|null
     */
    public static function dartCountSelectOptions(string $exerciseKey): ?array
    {
        return match ($exerciseKey) {
            'nine_darts' => [
                ['value' => 36, 'label' => '9 lotek'],
                ['value' => 72, 'label' => '18 lotek'],
            ],
            'clock_1_20_bull', 'doubles_clock', 'doubles_54' => [
                ['value' => 21, 'label' => '21 lotek'],
            ],
            'shanghai_10_15' => [
                ['value' => 18, 'label' => '18 lotek'],
            ],
            'shanghai_16_20' => [
                ['value' => 15, 'label' => '15 lotek'],
            ],
            default => null,
        };
    }

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
        self::assertAllowedDartCount($exerciseKey, $dartCount);

        return [
            'durationSeconds' => null,
            'dartCount' => $dartCount,
        ];
    }

    private static function assertAllowedDartCount(string $exerciseKey, ?int $dartCount): void
    {
        $allowed = self::allowedDartCounts($exerciseKey);
        if ($allowed === null || $dartCount === null) {
            return;
        }
        if (in_array($dartCount, $allowed, true)) {
            return;
        }

        throw new TrainingException(
            'Ta liczba lotek nie jest dozwolona dla tego ćwiczenia.',
            'training_definition_invalid',
        );
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
