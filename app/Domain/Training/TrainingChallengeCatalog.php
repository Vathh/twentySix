<?php

namespace App\Domain\Training;

/**
 * Stały katalog wyzwań — jak ćwiczenia. Nie tworzy się ich z panelu.
 * Administrator włącza / wyłącza i ustawia progi odznaki.
 */
final class TrainingChallengeCatalog
{
    public const FIFTY_FOUR_DART = 'fifty_four_dart';

    /**
     * @return array<string, array{name: string, description: string|null}>
     */
    public static function all(): array
    {
        return [
            self::FIFTY_FOUR_DART => [
                'name' => '54-Dart Challenge',
                'description' => 'Trzy etapy, 54 rzuty: Shanghai 10–15, Doubles, Shanghai 16–20.',
            ],
        ];
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
     * Domyślne progi odznaki (Żelazo→Diament). Admin może zmienić przed pierwszą aktywacją.
     *
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
