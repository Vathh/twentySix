<?php

namespace App\Domain\Tournament;

use App\Enums\GameStage;
use App\Support\Tournament\TournamentOverallPlaceCalculator;

/**
 * Miejsca overall dla drabinki pocieszenia: offset = rozmiar drabinki głównej.
 */
final class ConsolationPlacement
{
    public static function podiumWinnerPlace(GameStage $stage, int $mainBracketSize): ?int
    {
        return match ($stage) {
            GameStage::FINAL => $mainBracketSize + 1,
            GameStage::THIRD => $mainBracketSize + 3,
            default => null,
        };
    }

    public static function sharedPlace(
        GameStage $stage,
        int $mainBracketSize,
        int $consolationBracketSize,
    ): ?int {
        $sePlace = (new TournamentOverallPlaceCalculator)->sharedPlace($consolationBracketSize, $stage);

        if ($sePlace === null) {
            return null;
        }

        return $mainBracketSize + $sePlace;
    }
}
