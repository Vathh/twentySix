<?php

namespace App\Domain\GameScoring;

use DomainException;

/**
 * Limit lotek na lega X01 oraz próg przegranej (H2H).
 */
final class DartLimitRules
{
    public const DEFAULT_DART_LIMIT = 45;

    public const MIN_DART_LIMIT = 15;

    public const MAX_DART_LIMIT = 99;

    public const DART_LIMIT_STEP = 3;

    public const DEFAULT_LOSS_THRESHOLD = 50;

    public const MIN_LOSS_THRESHOLD = 2;

    public const MAX_LOSS_THRESHOLD = 170;

    public const OUTCOME_BULL_OFF = 'bull_off';

    public const OUTCOME_AUTO_P1 = 'auto_p1';

    public const OUTCOME_AUTO_P2 = 'auto_p2';

    public const CLOSE_CHECKOUT = 'checkout';

    public const CLOSE_BULL_OFF = 'bull_off';

    public const CLOSE_LOSS_THRESHOLD = 'loss_threshold';

    public const SCORING_EACH_OWN = 'each_own';

    public static function normalizeDartLimit(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $n = (int) $value;
        if ($n <= 0) {
            return null;
        }

        return self::snapDartLimit($n);
    }

    public static function snapDartLimit(int $value): int
    {
        $step = self::DART_LIMIT_STEP;
        $snapped = (int) (round($value / $step) * $step);
        if ($snapped < self::MIN_DART_LIMIT) {
            return self::MIN_DART_LIMIT;
        }
        if ($snapped > self::MAX_DART_LIMIT) {
            return self::MAX_DART_LIMIT;
        }

        return $snapped;
    }

    public static function normalizeLossThreshold(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $n = (int) $value;
        if ($n <= 0) {
            return null;
        }

        return max(self::MIN_LOSS_THRESHOLD, min(self::MAX_LOSS_THRESHOLD, $n));
    }

    public static function isApplicable(?int $dartLimit, bool $isX01, ?string $scoringMode = null): bool
    {
        if (! $isX01 || $dartLimit === null) {
            return false;
        }

        if ($scoringMode !== null && strtolower($scoringMode) === self::SCORING_EACH_OWN) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int>  $dartsByPlayerId
     */
    public static function isReached(?int $dartLimit, array $dartsByPlayerId): bool
    {
        if ($dartLimit === null || $dartsByPlayerId === []) {
            return false;
        }

        foreach ($dartsByPlayerId as $darts) {
            if ((int) $darts < $dartLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return self::OUTCOME_*
     */
    public static function resolveH2hOutcome(?int $lossThreshold, int $player1Remaining, int $player2Remaining): string
    {
        if ($lossThreshold === null) {
            return self::OUTCOME_BULL_OFF;
        }

        $p1Above = $player1Remaining >= $lossThreshold;
        $p2Above = $player2Remaining >= $lossThreshold;

        if ($p1Above && ! $p2Above) {
            return self::OUTCOME_AUTO_P2;
        }
        if ($p2Above && ! $p1Above) {
            return self::OUTCOME_AUTO_P1;
        }

        return self::OUTCOME_BULL_OFF;
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $visits
     * @param  array<int, int>  $playerIds
     * @return array<int, int>
     */
    public static function dartsByPlayerId(iterable $visits, array $playerIds): array
    {
        $out = array_fill_keys($playerIds, 0);
        foreach ($visits as $visit) {
            if (is_array($visit)) {
                $playerId = (int) ($visit['playerId'] ?? $visit['player_id'] ?? 0);
                $darts = (int) ($visit['dartsInVisit'] ?? $visit['darts_in_visit'] ?? 0);
            } else {
                $playerId = (int) $visit->player_id;
                $darts = (int) $visit->darts_in_visit;
            }
            if (array_key_exists($playerId, $out)) {
                $out[$playerId] += $darts;
            }
        }

        return $out;
    }

    public static function assertValidDartLimit(?int $dartLimit): void
    {
        if ($dartLimit === null) {
            return;
        }

        if (
            $dartLimit < self::MIN_DART_LIMIT
            || $dartLimit > self::MAX_DART_LIMIT
            || $dartLimit % self::DART_LIMIT_STEP !== 0
        ) {
            throw new DomainException('Ogranicznik lotek: 15–99, wielokrotność 3.');
        }
    }

    public static function assertValidLossThreshold(?int $lossThreshold, ?int $dartLimit): void
    {
        if ($lossThreshold === null) {
            return;
        }

        if ($dartLimit === null) {
            throw new DomainException('Próg przegranej wymaga włączonego ogranicznika lotek.');
        }

        if ($lossThreshold < self::MIN_LOSS_THRESHOLD || $lossThreshold > self::MAX_LOSS_THRESHOLD) {
            throw new DomainException('Próg przegranej: 2–170.');
        }
    }
}
