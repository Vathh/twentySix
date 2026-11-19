<?php

namespace App\Domain\Training;

enum TrainingKind: string
{
    case Exercise = 'exercise';
    case Plan = 'plan';
    case Challenge = 'challenge';
    case Event = 'event';

    public function slug(): string
    {
        return match ($this) {
            self::Exercise => 'exercises',
            self::Plan => 'plans',
            self::Challenge => 'challenges',
            self::Event => 'events',
        };
    }

    public static function tryFromSlug(string $slug): ?self
    {
        return match ($slug) {
            'exercises' => self::Exercise,
            'plans' => self::Plan,
            'challenges' => self::Challenge,
            'events' => self::Event,
            default => null,
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::Exercise => 'Ćwiczenie',
            self::Plan => 'Plan',
            self::Challenge => 'Wyzwanie',
            self::Event => 'Event',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Exercise => 'Ćwiczenia',
            self::Plan => 'Plany',
            self::Challenge => 'Wyzwania',
            self::Event => 'Eventy',
        };
    }

    public function activeSentence(bool $active): string
    {
        return match ($this) {
            self::Exercise => $active ? 'Ćwiczenie jest aktywne.' : 'Ćwiczenie jest nieaktywne.',
            self::Plan => $active ? 'Plan jest aktywny.' : 'Plan jest nieaktywny.',
            self::Challenge => $active ? 'Wyzwanie jest aktywne.' : 'Wyzwanie jest nieaktywne.',
            self::Event => $active ? 'Event jest aktywny.' : 'Event jest nieaktywny.',
        };
    }

    public function cancelledSentence(): string
    {
        return match ($this) {
            self::Plan => 'Plan został anulowany.',
            self::Challenge => 'Wyzwanie zostało anulowane.',
            self::Event => 'Event został anulowany.',
            self::Exercise => 'Ćwiczenie zostało anulowane.',
        };
    }

    public function removedSentence(): string
    {
        return match ($this) {
            self::Exercise => 'Ćwiczenie zostało usunięte.',
            self::Event => 'Event został usunięty.',
            self::Plan => 'Plan został usunięty.',
            self::Challenge => 'Wyzwanie zostało usunięte.',
        };
    }
}
