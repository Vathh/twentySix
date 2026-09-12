<?php

namespace App\Domain\Game;

/**
 * Wolny los w drabince to jawny gracz BYE, nie puste player_id.
 * null w slocie oznacza TBD — oczekiwanie na zwycięzcę z poprzedniej rundy.
 */
final class PlayoffBye
{
    public const DISPLAY_NAME = 'BYE';

    public static function isByeId(?int $playerId, int $byePlayerId): bool
    {
        return $playerId !== null && $playerId === $byePlayerId;
    }

    /**
     * Zwycięzca walkowera, albo null gdy to nie jest jeszcze mecz bye
     * (brakuje zawodnika / dwóch żywych graczy).
     */
    public static function advanceWinnerId(?int $player1Id, ?int $player2Id, int $byePlayerId): ?int
    {
        if ($player1Id === null || $player2Id === null) {
            return null;
        }

        $p1Bye = self::isByeId($player1Id, $byePlayerId);
        $p2Bye = self::isByeId($player2Id, $byePlayerId);

        if ($p1Bye && $p2Bye) {
            return $byePlayerId;
        }

        if ($p1Bye xor $p2Bye) {
            return $p1Bye ? $player2Id : $player1Id;
        }

        return null;
    }
}
