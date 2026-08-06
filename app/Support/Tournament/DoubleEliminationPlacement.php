<?php

namespace App\Support\Tournament;

use App\Enums\BracketSide;
use App\Enums\GameStage;

/**
 * Miejsca w DE z rundy drugiej porażki (LB) / grand final.
 */
final class DoubleEliminationPlacement
{
    /**
     * Miejsce dla przegranego meczu LB (druga porażka) lub null jeśli jeszcze nie eliminacja.
     */
    public static function placeForLoser(
        BracketSide $side,
        string $round,
        string $slot,
        int $bracketSize,
        bool $hasLoserDestination,
    ): ?int {
        if ($hasLoserDestination) {
            return null; // spadek do LB / dalsza gra
        }

        if ($side === BracketSide::GrandFinal) {
            return null; // podium 1/2 ustawiane osobno
        }

        if ($side !== BracketSide::Losers) {
            return null;
        }

        if (! preg_match('/^L(\d+)$/', $round, $m)) {
            return null;
        }

        $lbRound = (int) $m[1];
        $wbRounds = (int) log($bracketSize, 2);
        $lbRoundCount = 2 * $wbRounds - 2;
        $roundsFromEnd = $lbRoundCount - 1 - $lbRound;

        // LB Final (ostatnia) → 3; poprzednia → 4; wcześniej ex aequo 5,7,9…
        if ($roundsFromEnd === 0) {
            return 3;
        }
        if ($roundsFromEnd === 1) {
            return 4;
        }

        return 5 + ($roundsFromEnd - 2) * 2;
    }

    public static function resultStageForRound(string $round): GameStage
    {
        return match (true) {
            $round === 'GF', $round === 'GF2', $round === 'GF1' => GameStage::FINAL,
            str_starts_with($round, 'L') => GameStage::SEMI,
            default => GameStage::QUARTER,
        };
    }
}
