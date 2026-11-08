<?php

namespace App\Domain\Moderation;

enum ReportReason: string
{
    case Spam = 'spam';
    case Insult = 'insult';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Insult => 'Obraza',
            self::Other => 'Inne',
        };
    }
}
