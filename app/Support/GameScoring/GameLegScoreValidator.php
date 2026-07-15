<?php

namespace App\Support\GameScoring;

use DomainException;

final class GameLegScoreValidator
{
    /**
     * Waliduje wynik meczu zgodnie z formatem (legi przy 1 secie, sety przy wielu setach).
     *
     * @return int winnerId
     */
    public static function validateAndResolveWinner(
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        MatchFormat $format,
    ): int {
        $toWin = $format->scoreToWin();
        $unit = $format->scoreUnit();

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
