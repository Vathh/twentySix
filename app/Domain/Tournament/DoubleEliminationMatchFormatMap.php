<?php

namespace App\Domain\Tournament;

use App\Enums\GameStage;

/**
 * Formularz startu zapisuje formaty pod kluczami SE (QUARTER/SEMI/FINAL).
 * Mecze DE mają rundy W0/L3/GF — tu mapowanie, żeby szczebel z kreatora trafił na mecz.
 */
final class DoubleEliminationMatchFormatMap
{
    public static function stageForRound(string $round, int $bracketSize): GameStage
    {
        if (in_array($round, ['GF', 'GF1', 'GF2'], true)) {
            return GameStage::FINAL;
        }

        if (preg_match('/^W(\d+)$/', $round, $m) === 1) {
            $matchesInRound = intdiv($bracketSize, 2 ** ((int) $m[1] + 1));

            return self::stageForMatchCount(max(1, $matchesInRound));
        }

        if (preg_match('/^L(\d+)$/', $round, $m) === 1) {
            $wbRounds = (int) log($bracketSize, 2);
            $lbRound = (int) $m[1];
            $lbRoundCount = 2 * $wbRounds - 2;

            if ($lbRound === $lbRoundCount - 1) {
                return GameStage::FINAL;
            }

            $matchesInRound = self::lbMatchCount($lbRound, $bracketSize);
            if ($matchesInRound <= 1) {
                return GameStage::SEMI;
            }

            return self::stageForMatchCount($matchesInRound);
        }

        return GameStage::FINAL;
    }

    private static function stageForMatchCount(int $matchesInRound): GameStage
    {
        return match ($matchesInRound) {
            64 => GameStage::SIXTYFOUR,
            32 => GameStage::THIRTYTWO,
            16 => GameStage::SIXTEEN,
            8 => GameStage::EIGHT,
            4 => GameStage::QUARTER,
            2 => GameStage::SEMI,
            default => GameStage::FINAL,
        };
    }

    private static function lbMatchCount(int $lbRound, int $bracketSize): int
    {
        $effective = $lbRound - ($lbRound % 2);

        return max(1, intdiv($bracketSize, 2 ** (intdiv($effective, 2) + 2)));
    }
}
