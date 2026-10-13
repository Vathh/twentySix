<?php

namespace App\Domain\Badge\Qf;

final class QfLevelPolicy
{
    /** @var list<string> */
    public const LEVELS = ['iron', 'bronze', 'gold', 'platinum', 'diamond'];

    /** @var array<string, string> */
    public const LABELS = [
        'iron' => 'Żelazo',
        'bronze' => 'Brąz',
        'gold' => 'Złoto',
        'platinum' => 'Platyna',
        'diamond' => 'Diament',
    ];

    /** @var list<int> */
    private const BAND_16_19 = [1, 3, 10, 25, 50];

    /** @var list<int> */
    private const BAND_13_15 = [1, 2, 5, 10, 25];

    /** @var list<int> */
    private const BAND_10_12 = [1, 2, 3, 5, 7];

    /** @var list<int> */
    private const BAND_9 = [1, 2, 3, 4, 5];

    /**
     * @return list<int>
     */
    public static function thresholds(string $key): array
    {
        $darts = (int) $key;

        return match (true) {
            $darts === 9 => self::BAND_9,
            $darts >= 10 && $darts <= 12 => self::BAND_10_12,
            $darts >= 13 && $darts <= 15 => self::BAND_13_15,
            default => self::BAND_16_19,
        };
    }

    public static function levelForHits(string $key, int $hits): int
    {
        $level = 0;
        foreach (self::thresholds($key) as $threshold) {
            if ($hits >= $threshold) {
                $level = $threshold;
            }
        }

        return $level;
    }

    public static function nameForHits(string $key, int $hits): string
    {
        $name = 'locked';
        foreach (self::thresholds($key) as $index => $threshold) {
            if ($hits >= $threshold) {
                $name = self::LEVELS[$index];
            }
        }

        return $name;
    }

    public static function label(?string $levelName): ?string
    {
        if ($levelName === null) {
            return null;
        }

        return self::LABELS[$levelName] ?? null;
    }

    public static function numberForName(string $levelName): int
    {
        $index = array_search($levelName, self::LEVELS, true);

        return $index === false ? 0 : $index + 1;
    }

    public static function nextThreshold(string $key, int $hits): ?int
    {
        foreach (self::thresholds($key) as $threshold) {
            if ($hits < $threshold) {
                return $threshold;
            }
        }

        return null;
    }

    public static function nextLevelName(string $key, int $hits): ?string
    {
        foreach (self::thresholds($key) as $index => $threshold) {
            if ($hits < $threshold) {
                return self::LEVELS[$index];
            }
        }

        return null;
    }

    /**
     * @return array{percent: int, label: string}
     */
    public static function progress(string $key, int $hits): array
    {
        $next = self::nextThreshold($key, $hits);
        if ($next === null) {
            return [
                'percent' => 100,
                'label' => (string) $hits,
            ];
        }

        $percent = $next > 0 ? (int) floor(($hits / $next) * 100) : 0;

        return [
            'percent' => max(0, min(100, $percent)),
            'label' => $hits.'/'.$next,
        ];
    }
}
