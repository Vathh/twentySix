<?php

namespace App\Support\Tournament;

use App\Enums\GameStage;
use InvalidArgumentException;

/**
 * Identyfikatory slotów drabinki (stringi w DB) — wspólne dla groups_playoff / SE / (później DE).
 */
final class PlayoffSlotIds
{
    public const FINAL = 'FINAL';

    public const THIRD = 'THIRD';

    public const CONSOLATION_PREFIX = 'C_';

    public static function withPrefix(string $slot, string $prefix): string
    {
        return $prefix === '' ? $slot : $prefix.$slot;
    }

    public static function unprefixed(string $slot): string
    {
        return str_starts_with($slot, self::CONSOLATION_PREFIX)
            ? substr($slot, strlen(self::CONSOLATION_PREFIX))
            : $slot;
    }

    public static function isFinalSlot(string $slot): bool
    {
        return self::unprefixed($slot) === self::FINAL;
    }

    public static function thirdSlotForFinal(string $finalSlot): string
    {
        return str_starts_with($finalSlot, self::CONSOLATION_PREFIX)
            ? self::CONSOLATION_PREFIX.self::THIRD
            : self::THIRD;
    }

    public static function forStage(GameStage $stage, int $index1Based): string
    {
        return match ($stage) {
            GameStage::FINAL => self::FINAL,
            GameStage::THIRD => self::THIRD,
            GameStage::SEMI => 'SEMI_'.$index1Based,
            GameStage::QUARTER => 'QF_'.$index1Based,
            GameStage::EIGHT => 'EIGHT_'.$index1Based,
            GameStage::SIXTEEN => 'SIXTEEN_'.$index1Based,
            GameStage::THIRTYTWO => 'THIRTYTWO_'.$index1Based,
            GameStage::SIXTYFOUR => 'SIXTYFOUR_'.$index1Based,
            default => throw new InvalidArgumentException('Etap nie należy do drabinki: '.$stage->value),
        };
    }

    public static function stageForFirstRoundMatchCount(int $matchesInRound): GameStage
    {
        return match ($matchesInRound) {
            64 => GameStage::SIXTYFOUR,
            32 => GameStage::THIRTYTWO,
            16 => GameStage::SIXTEEN,
            8 => GameStage::EIGHT,
            4 => GameStage::QUARTER,
            2 => GameStage::SEMI,
            1 => GameStage::FINAL,
            default => throw new InvalidArgumentException(
                "Nieobsługiwana liczba meczów w rundzie: {$matchesInRound}.",
            ),
        };
    }

    public static function destination(string $targetSlot, string $playerSlotAB): string
    {
        return $targetSlot.'-'.$playerSlotAB;
    }

    public static function isTerminal(string $slot): bool
    {
        $bare = self::unprefixed($slot);

        return $bare === self::FINAL || $bare === self::THIRD;
    }
}
