<?php

namespace App\Support\GameScoring;

use DomainException;

final class GameLegScoreValidator
{
    /**
     * @return array{0: int, 1: int} winnerId
     */
    public static function validateAndResolveWinner(
        int $player1Id,
        int $player2Id,
        int $player1Score,
        int $player2Score,
        int $legsToWin = 2,
    ): int {
        if ($player1Score < 0 || $player2Score < 0) {
            throw new DomainException('Wynik w legach nie może być ujemny.');
        }

        if ($player1Score > $legsToWin || $player2Score > $legsToWin) {
            throw new DomainException("Maksymalny wynik to {$legsToWin} legi.");
        }

        if ($player1Score === $player2Score) {
            throw new DomainException('Mecz musi mieć zwycięzcę — wyniki nie mogą być remisowe.');
        }

        if ($player1Score !== $legsToWin && $player2Score !== $legsToWin) {
            throw new DomainException("Jeden z graczy musi wygrać {$legsToWin} legi.");
        }

        if ($player1Score + $player2Score > $legsToWin + ($legsToWin - 1)) {
            throw new DomainException('Nieprawidłowy wynik meczu.');
        }

        return $player1Score > $player2Score ? $player1Id : $player2Id;
    }

    public static function walkoverScores(int $winnerPlayerId, int $player1Id, int $legsToWin = 2): array
    {
        if ($winnerPlayerId === $player1Id) {
            return [$legsToWin, 0];
        }

        return [0, $legsToWin];
    }
}
