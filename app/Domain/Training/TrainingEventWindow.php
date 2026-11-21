<?php

namespace App\Domain\Training;

use Carbon\CarbonImmutable;

/**
 * Okna prób eventu: tydzień kalendarzowy, bloki 7 dni od startu, bloki od kotwicy.
 */
final class TrainingEventWindow
{
    public const MODE_CALENDAR_WEEK = 'calendar_week';

    public const MODE_FROM_START = 'from_start';

    public const MODE_FROM_ANCHOR = 'from_anchor';

    public const DEFAULT_TIMEOUT_HOURS = 4;

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_CALENDAR_WEEK,
            self::MODE_FROM_START,
            self::MODE_FROM_ANCHOR,
        ];
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::MODE_CALENDAR_WEEK => 'Tydzień kalendarzowy (pon–niedz)',
            self::MODE_FROM_START => 'Bloki 7 dni od startu eventu',
            self::MODE_FROM_ANCHOR => 'Bloki 7 dni od wskazanej daty',
            default => $mode,
        };
    }

    /**
     * Klucz okna dla chwili $at (np. "2026-W47" albo "2026-08-25").
     */
    public static function key(
        string $mode,
        CarbonImmutable $at,
        CarbonImmutable $eventStartsAt,
        ?CarbonImmutable $anchorDate,
    ): string {
        return match ($mode) {
            self::MODE_CALENDAR_WEEK => $at->startOfWeek(CarbonImmutable::MONDAY)->format('o-\WW'),
            self::MODE_FROM_START => self::blockKey($at, $eventStartsAt->startOfDay()),
            self::MODE_FROM_ANCHOR => self::blockKey(
                $at,
                ($anchorDate ?? $eventStartsAt)->startOfDay(),
            ),
            default => throw new TrainingException(
                'Nieznany tryb okna prób.',
                'training_event_window_invalid',
            ),
        };
    }

    private static function blockKey(CarbonImmutable $at, CarbonImmutable $origin): string
    {
        $day = $at->startOfDay();
        $base = $origin->startOfDay();
        if ($day->lt($base)) {
            return $base->toDateString();
        }
        $days = $base->diffInDays($day);
        $block = intdiv($days, 7);
        return $base->addDays($block * 7)->toDateString();
    }
}
