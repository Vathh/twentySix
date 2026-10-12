<?php

namespace App\Domain\Badge\HighVisit;

final class HighVisitCatalog
{
    /** @var list<string> */
    public const KEYS = ['100', '140', '180'];

    public static function keyForScore(int $score): ?string
    {
        if ($score === 180) {
            return '180';
        }
        if ($score >= 140 && $score < 180) {
            return '140';
        }
        if ($score >= 100 && $score < 140) {
            return '100';
        }

        return null;
    }

    public static function label(string $key): string
    {
        return match ($key) {
            '180' => '180',
            '140' => '140+',
            '100' => '100+',
            default => $key,
        };
    }

    public static function imagePath(string $key, string $levelName): string
    {
        $file = $levelName === 'locked' ? 'iron' : $levelName;

        return "images/badges/visits/{$key}/{$file}.png";
    }
}
