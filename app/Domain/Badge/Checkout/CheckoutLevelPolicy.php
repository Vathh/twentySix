<?php

namespace App\Domain\Badge\Checkout;

final class CheckoutLevelPolicy
{
    public const APEX = 10;

    public const BRIGHT = 7;

    public const GOLD = 5;

    public const BRONZE = 3;

    public const IRON = 1;

    /** @var list<int> */
    public const THRESHOLDS = [0, self::IRON, self::BRONZE, self::GOLD, self::BRIGHT, self::APEX];

    public static function levelForHits(int $hits): int
    {
        $level = 0;
        foreach ([self::IRON, self::BRONZE, self::GOLD, self::BRIGHT, self::APEX] as $threshold) {
            if ($hits >= $threshold) {
                $level = $threshold;
            }
        }

        return $level;
    }

    public static function nameForHits(int $hits): string
    {
        return match (self::levelForHits($hits)) {
            self::APEX => 'apex',
            self::BRIGHT => 'bright',
            self::GOLD => 'gold',
            self::BRONZE => 'bronze',
            self::IRON => 'iron',
            default => 'locked',
        };
    }
}
