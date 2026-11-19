<?php

namespace App\Domain\Training;

enum TrainingDefinitionStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Paused = 'paused';
    case Closing = 'closing';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Nieaktywne',
            self::Active => 'Aktywne',
            self::Paused => 'Wstrzymane',
            self::Closing => 'Kończy się',
            self::Finished => 'Zakończone',
            self::Cancelled => 'Anulowane',
            self::Removed => 'Usunięte',
        };
    }

    public function listedInAdmin(): bool
    {
        return $this === self::Inactive || $this === self::Active;
    }
}
