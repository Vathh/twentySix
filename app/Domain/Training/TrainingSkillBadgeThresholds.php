<?php

namespace App\Domain\Training;

/**
 * Progi odznak Scoring / Doubles / Checkouty (trwałe, nie spadają).
 */
final class TrainingSkillBadgeThresholds
{
    /**
     * @return list<array{level: string, average: float, shareAtLeast60: float}>
     */
    public static function scoring(): array
    {
        return [
            ['level' => 'iron', 'average' => 40.0, 'shareAtLeast60' => 0.20],
            ['level' => 'bronze', 'average' => 50.0, 'shareAtLeast60' => 0.25],
            ['level' => 'gold', 'average' => 60.0, 'shareAtLeast60' => 0.30],
            ['level' => 'platinum', 'average' => 65.0, 'shareAtLeast60' => 0.35],
            ['level' => 'diamond', 'average' => 70.0, 'shareAtLeast60' => 0.40],
        ];
    }

    /**
     * @return list<array{level: string, percent: float, hits: int}>
     */
    public static function doubles(): array
    {
        return [
            ['level' => 'iron', 'percent' => 10.0, 'hits' => 50],
            ['level' => 'bronze', 'percent' => 14.0, 'hits' => 100],
            ['level' => 'gold', 'percent' => 18.0, 'hits' => 200],
            ['level' => 'platinum', 'percent' => 22.0, 'hits' => 400],
            ['level' => 'diamond', 'percent' => 25.0, 'hits' => 1000],
        ];
    }

    /**
     * @return list<array{level: string, percent: float, closed: int}>
     */
    public static function checkouts(): array
    {
        return [
            ['level' => 'iron', 'percent' => 2.0, 'closed' => 20],
            ['level' => 'bronze', 'percent' => 4.0, 'closed' => 40],
            ['level' => 'gold', 'percent' => 7.0, 'closed' => 70],
            ['level' => 'platinum', 'percent' => 10.0, 'closed' => 110],
            ['level' => 'diamond', 'percent' => 15.0, 'closed' => 150],
        ];
    }
}
