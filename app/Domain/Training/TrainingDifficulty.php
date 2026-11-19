<?php

namespace App\Domain\Training;

enum TrainingDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Łatwy',
            self::Medium => 'Średni',
            self::Hard => 'Trudny',
        };
    }
}
