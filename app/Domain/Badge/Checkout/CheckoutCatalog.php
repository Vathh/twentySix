<?php

namespace App\Domain\Badge\Checkout;

final class CheckoutCatalog
{
    /** Niemożliwe finishy 100–170 (bogey numbers). */
    private const EXCLUDED = [159, 162, 163, 165, 166, 168, 169];

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        $keys = [];
        for ($score = 100; $score <= 170; $score++) {
            if (in_array($score, self::EXCLUDED, true)) {
                continue;
            }
            $keys[] = $score;
        }

        return $keys;
    }

    public static function contains(int $score): bool
    {
        return $score >= 100 && $score <= 170 && ! in_array($score, self::EXCLUDED, true);
    }

    public static function count(): int
    {
        return count(self::all());
    }

    public static function description(int $score): string
    {
        return "Checkout {$score} zamykający lega.";
    }
}
