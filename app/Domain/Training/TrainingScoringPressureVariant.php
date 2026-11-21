<?php

namespace App\Domain\Training;

/**
 * Wariant Scoring z presją w slocie planu. Z biblioteki gracz wybiera sam;
 * w planie ustawia administrator.
 */
final class TrainingScoringPressureVariant
{
    public const VALUES = ['20', '19', '18', '20/19/18'];

    public static function assert(?string $value): string
    {
        if ($value === null || $value === '') {
            return '20';
        }
        if (! in_array($value, self::VALUES, true)) {
            throw new TrainingException(
                'Nieznany wariant Scoringu z presją.',
                'training_definition_invalid',
            );
        }

        return $value;
    }
}
