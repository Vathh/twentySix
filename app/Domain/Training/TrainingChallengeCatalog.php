<?php

namespace App\Domain\Training;

/**
 * Dawny katalog gotowych wyzwań. Wyzwania składa się teraz z ćwiczeń w panelu.
 * Klasa zostaje pod legacy (stare mechanic / testy / findCatalogChallenge).
 */
final class TrainingChallengeCatalog
{
    /** @deprecated Katalogowe 54-Dart usunięte — składa się z trzech ćwiczeń. */
    public const FIFTY_FOUR_DART = 'fifty_four_dart';

    /**
     * @return array<string, array{name: string, description: string|null}>
     */
    public static function all(): array
    {
        return [];
    }

    public static function exists(string $challengeKey): bool
    {
        return array_key_exists($challengeKey, self::all());
    }

    public static function name(string $challengeKey): string
    {
        return self::all()[$challengeKey]['name'] ?? $challengeKey;
    }

    public static function description(string $challengeKey): ?string
    {
        return self::all()[$challengeKey]['description'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::all() as $key => $meta) {
            $labels[$key] = $meta['name'];
        }

        return $labels;
    }

    /** @deprecated użyj name() — zostawione pod stare wywołania */
    public static function label(?string $challengeKey): ?string
    {
        if ($challengeKey === null || $challengeKey === '') {
            return null;
        }

        return self::name($challengeKey);
    }

    /**
     * @return array<string, int>
     */
    public static function defaultBadgeThresholds(string $challengeKey): array
    {
        unset($challengeKey);

        return [
            'iron' => 500,
            'bronze' => 1000,
            'gold' => 1500,
            'platinum' => 2000,
            'diamond' => 2500,
        ];
    }
}
