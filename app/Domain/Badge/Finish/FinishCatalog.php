<?php

namespace App\Domain\Badge\Finish;

final class FinishCatalog
{
    /** @var list<string> */
    public const KEYS = ['100'];

    public static function keyForScore(int $score): ?string
    {
        if ($score < 100) {
            return null;
        }

        return '100';
    }

    public static function label(string $key): string
    {
        return $key === '100' ? '100+' : $key;
    }

    public static function imagePath(string $key, string $levelName): string
    {
        $file = $levelName === 'locked' ? 'iron' : $levelName;

        return "images/badges/finish/{$key}/{$file}.png";
    }
}
