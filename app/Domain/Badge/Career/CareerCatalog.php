<?php

namespace App\Domain\Badge\Career;

final class CareerCatalog
{
    public const PODIUM = 'podium';

    public const WIN_STREAK = 'win_streak';

    public const APPEARANCES = 'appearances';

    public const TITLES = 'titles';

    public const SOURCE_KIND = 'tournament';

    /** @var list<string> */
    public const KEYS = [
        self::PODIUM,
        self::WIN_STREAK,
        self::APPEARANCES,
        self::TITLES,
    ];

    public static function label(string $key): string
    {
        return match ($key) {
            self::PODIUM => 'Podium',
            self::WIN_STREAK => 'Seria zwycięstw',
            self::APPEARANCES => 'Turniejowiec',
            self::TITLES => 'Zwycięzca turniejów',
            default => $key,
        };
    }

    public static function description(string $key): string
    {
        return match ($key) {
            self::PODIUM => 'Miejsca 1–3 w turniejach.',
            self::WIN_STREAK => 'Rekord serii wygranych meczów turniejowych i ligowych.',
            self::APPEARANCES => 'Udziały w turniejach.',
            self::TITLES => 'Wygrane turnieje.',
            default => '',
        };
    }

    public static function imagePath(string $key, string $levelName): string
    {
        $file = $levelName === 'locked' ? 'iron' : $levelName;

        return "images/badges/career/{$key}/{$file}.png";
    }
}
