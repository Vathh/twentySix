<?php

namespace App\Enums;

enum TournamentInvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case WITHDRAWN = 'withdrawn';
    case REMOVED = 'removed';

    public function isActive(): bool
    {
        return $this === self::PENDING || $this === self::ACCEPTED;
    }

    public function canReinvite(): bool
    {
        return ! $this->isActive();
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Oczekuje',
            self::ACCEPTED => 'Zaakceptowane',
            self::REJECTED => 'Odrzucone',
            self::CANCELLED => 'Anulowane',
            self::WITHDRAWN => 'Wycofane',
            self::REMOVED => 'Usunięty',
        };
    }
}
