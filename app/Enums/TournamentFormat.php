<?php

namespace App\Enums;

enum TournamentFormat: string
{
    case GroupsPlayoff = 'groups_playoff';
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';

    public function label(): string
    {
        return match ($this) {
            self::GroupsPlayoff => 'Grupy + drabinka',
            self::SingleElimination => 'Single elimination',
            self::DoubleElimination => 'Double elimination',
        };
    }

    public function hasGroupStage(): bool
    {
        return $this === self::GroupsPlayoff;
    }

    public function isEliminationOnly(): bool
    {
        return $this === self::SingleElimination || $this === self::DoubleElimination;
    }
}
