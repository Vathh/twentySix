<?php

namespace App\Domain\Training;

/**
 * Grupy odznak treningowych. Naliczanie z ćwiczeń — numer 13.
 */
final class TrainingBadgeGroups
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'scoring', 'name' => 'Scoring'],
            ['id' => 'doubles', 'name' => 'Doubles'],
            ['id' => 'checkouts', 'name' => 'Checkouty'],
            ['id' => 'challenges', 'name' => 'Wyzwania'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function levels(): array
    {
        return ['iron', 'bronze', 'gold', 'platinum', 'diamond'];
    }
}
