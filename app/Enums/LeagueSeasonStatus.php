<?php

namespace App\Enums;

enum LeagueSeasonStatus: string
{
    case DRAFT = 'draft';
    case IN_PROGRESS = 'in_progress';
    case PLAYOFFS = 'playoffs';
    case FINISHED = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Szkic',
            self::IN_PROGRESS => 'W trakcie',
            self::PLAYOFFS => 'Baraże',
            self::FINISHED => 'Zakończony',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::IN_PROGRESS || $this === self::PLAYOFFS;
    }

    public function locksPyramid(): bool
    {
        return $this === self::IN_PROGRESS || $this === self::PLAYOFFS;
    }
}
