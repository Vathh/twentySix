<?php

namespace App\Domain\Training;

/**
 * Warianty ćwiczeń ulubionych / wyświetlanych jako osobne pozycje startowe.
 */
final class TrainingExerciseVariant
{
    public static function normalize(?string $variant): string
    {
        if ($variant === null) {
            return '';
        }

        return trim($variant);
    }

    public static function assertAllowed(string $exerciseKey, string $variant): void
    {
        $variant = self::normalize($variant);
        $allowed = self::allowed($exerciseKey);
        if ($allowed === null) {
            if ($variant !== '') {
                throw new TrainingException(
                    'To ćwiczenie nie ma wariantów.',
                    'training_session_invalid',
                );
            }

            return;
        }
        if ($variant === '' || ! in_array($variant, $allowed, true)) {
            throw new TrainingException(
                'Nieznany wariant ćwiczenia.',
                'training_session_invalid',
            );
        }
    }

    /**
     * null = ćwiczenie bez wariantu (variant musi być pusty).
     * lista = obowiązkowy wariant z listy.
     * dla zegara/boba wariant jest zakodowany w exercise_key — variant pusty.
     *
     * @return list<string>|null
     */
    public static function allowed(string $exerciseKey): ?array
    {
        return match ($exerciseKey) {
            'nine_darts' => ['9', '18'],
            'scoring_under_pressure' => ['20', '19', '18', '20/19/18'],
            default => null,
        };
    }

    public static function displayName(string $exerciseKey, string $variant = ''): string
    {
        $variant = self::normalize($variant);
        $base = TrainingExerciseCatalog::label($exerciseKey);

        return match ($exerciseKey) {
            'clock_1_20_bull' => 'Zegar — Szybki',
            'clock_sdt' => 'Zegar — Sektory',
            'doubles_clock' => 'Zegar — Double',
            'bobs_27_easy' => "Bob's 27 — Easy",
            'bobs_27_hard' => "Bob's 27 — Hard",
            'nine_darts' => $variant === '18' ? '9/18 lotek — 18 lotek' : '9/18 lotek — 9 lotek',
            'scoring_under_pressure' => $variant !== ''
                ? 'Scoring z presją — '.$variant
                : $base,
            default => $base,
        };
    }
}
