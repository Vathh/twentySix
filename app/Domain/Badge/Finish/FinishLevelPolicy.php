<?php

namespace App\Domain\Badge\Finish;

final class FinishLevelPolicy
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

    /** @var array<string, list<int>> */
    public const THRESHOLDS = [
        '100' => [5, 25, 100, 250, 1000],
    ];

    public static function levelForHits(string $key, int $hits): int
    {
        $level = 0;
        foreach (self::THRESHOLDS[$key] as $threshold) {
            if ($hits >= $threshold) {
                $level = $threshold;
            }
        }

        return $level;
    }

    public static function nameForHits(string $key, int $hits): string
    {
        $name = 'locked';
        foreach (self::THRESHOLDS[$key] as $index => $threshold) {
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
        foreach (self::THRESHOLDS[$key] as $threshold) {
            if ($hits < $threshold) {
                return $threshold;
            }
        }

        return null;
    }

    public static function nextLevelName(string $key, int $hits): ?string
    {
        foreach (self::THRESHOLDS[$key] as $index => $threshold) {
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
