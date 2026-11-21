<?php

namespace App\Domain\Training;

/**
 * Progi odznaki wyzwania: Żelazo → Diament. Naliczanie odznak — numer 13.
 */
final class TrainingBadgeThresholds
{
    /**
     * @return list<string>
     */
    public static function levels(): array
    {
        return TrainingBadgeGroups::levels();
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'iron' => 'Żelazo',
            'bronze' => 'Brąz',
            'gold' => 'Złoto',
            'platinum' => 'Platyna',
            'diamond' => 'Diament',
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, int>|null
     */
    public static function normalize(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new TrainingException(
                'Progi odznaki muszą być obiektem poziomów.',
                'training_badge_thresholds_invalid',
            );
        }

        $out = [];
        $previous = null;
        foreach (self::levels() as $level) {
            if (! array_key_exists($level, $value)) {
                throw new TrainingException(
                    'Wyzwanie wymaga pięciu progów odznaki.',
                    'training_badge_thresholds_invalid',
                );
            }
            $raw = $value[$level];
            if (! is_int($raw) && ! (is_string($raw) && preg_match('/^\d+$/', $raw) === 1)) {
                throw new TrainingException(
                    'Próg odznaki musi być liczbą całkowitą.',
                    'training_badge_thresholds_invalid',
                );
            }
            $n = (int) $raw;
            if ($n < 0 || $n > FiftyFourDartChallenge::MAX_SCORE) {
                throw new TrainingException(
                    'Próg odznaki jest poza zakresem wyniku.',
                    'training_badge_thresholds_invalid',
                );
            }
            if ($previous !== null && $n <= $previous) {
                throw new TrainingException(
                    'Progi odznaki muszą rosnąć.',
                    'training_badge_thresholds_invalid',
                );
            }
            $out[$level] = $n;
            $previous = $n;
        }

        $extra = array_diff(array_keys($value), self::levels());
        if ($extra !== []) {
            throw new TrainingException(
                'Nieznany poziom odznaki.',
                'training_badge_thresholds_invalid',
            );
        }

        return $out;
    }
}
