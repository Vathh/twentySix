<?php

namespace App\Domain\GameScoring;

use DomainException;

final class GameLegScoreValidator
{
    /**
     * Waliduje wynik meczu zgodnie z formatem (legi przy 1 secie, sety przy wielu setach).
     *
     * @return int|null winnerId albo null przy dopuszczalnym remisie
     */
    public static function validateAndResolveWinner(
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        MatchFormat $format,
    ): ?int {
        $toWin = $format->scoreToWin();
        $unit = $format->scoreUnit();

        if ($format->isBestOf() && $format->isSingleSet()) {
            return self::validateBestOfLegs(
                $player1Id,
                $player2Id,
                $player1Score,
                $player2Score,
                $format,
            );
        }

        return self::validateRaceWinner(
            $player1Id,
            $player2Id,
            $player1Score,
            $player2Score,
            $toWin,
            $unit,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function walkoverScores(
        int $winnerPlayerId,
        int $player1Id,
        MatchFormat $format,
    ): array {
        $win = $format->scoreToWin();

        if ($winnerPlayerId === $player1Id) {
            return [$win, 0];
        }

        return [0, $win];
    }

    /**
     * @return int|null
     */
    private static function validateBestOfLegs(
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        MatchFormat $format,
    ): ?int {
        $winAt = $format->legsToWinSet;
        $maxLegs = $format->winLength;

        if ($player1Score < 0 || $player2Score < 0) {
            throw new DomainException('Wynik w legach nie może być ujemny.');
        }
        if ($player1Score > $winAt || $player2Score > $winAt) {
            throw new DomainException("Maksymalny wynik to {$winAt} legów.");
        }

        $played = $player1Score + $player2Score;
        if ($played > $maxLegs) {
            throw new DomainException("Best of {$maxLegs}: za dużo rozegranych legów.");
        }

        if ($player1Score === $player2Score) {
            if ($format->allowsDraw() && $played === $maxLegs) {
                return null;
            }
            throw new DomainException('Mecz musi mieć zwycięzcę — wyniki nie mogą być remisowe.');
        }

        if ($player1Score !== $winAt && $player2Score !== $winAt && $played !== $maxLegs) {
            throw new DomainException("Jeden z graczy musi wygrać {$winAt} legów albo rozegrać {$maxLegs}.");
        }

        return $player1Score > $player2Score ? $player1Id : $player2Id;
    }

    /**
     * @return int winnerId
     */
    private static function validateRaceWinner(
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        int $toWin,
        string $unitLabel,
    ): int {
        if ($player1Score < 0 || $player2Score < 0) {
            throw new DomainException("Wynik w {$unitLabel} nie może być ujemny.");
        }

        if ($player1Score > $toWin || $player2Score > $toWin) {
            throw new DomainException("Maksymalny wynik to {$toWin} {$unitLabel}.");
        }

        if ($player1Score === $player2Score) {
            throw new DomainException('Mecz musi mieć zwycięzcę — wyniki nie mogą być remisowe.');
        }

        if ($player1Score !== $toWin && $player2Score !== $toWin) {
            throw new DomainException("Jeden z graczy musi wygrać {$toWin} {$unitLabel}.");
        }

        if ($player1Score + $player2Score > $toWin + ($toWin - 1)) {
            throw new DomainException('Nieprawidłowy wynik meczu.');
        }

        return $player1Score > $player2Score ? $player1Id : $player2Id;
    }
}
