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
        return $slot === self::FINAL || $slot === self::THIRD;
    }
}
