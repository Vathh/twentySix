<?php

namespace App\Enums;

enum TournamentJoinRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Oczekuje',
            self::APPROVED => 'Dołączony',
            self::REJECTED => 'Odrzucone',
            self::CANCELLED => 'Anulowane',
        };
    }
}
