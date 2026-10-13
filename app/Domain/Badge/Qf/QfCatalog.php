<?php

namespace App\Domain\Badge\Qf;

final class QfCatalog
{
    /** @var list<string> */
    public const KEYS = ['9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19'];

    public static function keyForDarts(int $darts): ?string
    {
        if ($darts < 9 || $darts > 19) {
            return null;
        }

        return (string) $darts;
    }

    public static function label(string $key): string
    {
        return $key;
    }

    public static function imagePath(string $key, string $levelName): string
    {
        $file = $levelName === 'locked' ? 'iron' : $levelName;

        return "images/badges/qf/{$key}/{$file}.png";
    }
}
