<?php

namespace App\Domain\Training;

/**
 * Mechaniki wyzwań znane panelowi. API nadal przyjmuje slug z fundamentu.
 */
final class TrainingChallengeCatalog
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'fifty_four_dart' => '54-Dart Challenge',
        ];
    }

    public static function label(?string $mechanic): ?string
    {
        if ($mechanic === null || $mechanic === '') {
            return null;
        }

        return self::all()[$mechanic] ?? $mechanic;
    }
}
