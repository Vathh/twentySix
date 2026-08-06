<?php

namespace App\Support\Tournament;

/**
 * Losowe pary R1 z bye (uzupełnienie do potęgi 2).
 */
final class PlayoffByePairing
{
    /**
     * @param  list<int>  $playerIds
     * @return list<array{0: int|null, 1: int|null}>
     */
    public static function pair(array $playerIds, int $bracketSize): array
    {
        $playerCount = count($playerIds);

        if ($playerCount < 1 || $playerCount > $bracketSize) {
            throw new \InvalidArgumentException(
                "Liczba graczy ({$playerCount}) musi być w zakresie 1–{$bracketSize}.",
            );
        }

        if (! TournamentStartRules::isPowerOfTwo($bracketSize)) {
            throw new \InvalidArgumentException("Rozmiar drabinki musi być potęgą 2 (otrzymano {$bracketSize}).");
        }

        $players = $playerIds;
        shuffle($players);

        $byeCount = $bracketSize - $playerCount;
        $byePositions = self::byePositions($bracketSize, $byeCount);

        $slots = array_fill(0, $bracketSize, null);
        $queue = $players;

        for ($i = 0; $i < $bracketSize; $i++) {
            if (isset($byePositions[$i])) {
                continue;
            }
            $slots[$i] = array_shift($queue);
        }

        $pairs = [];
        for ($i = 0; $i < $bracketSize; $i += 2) {
            $pairs[] = [$slots[$i], $slots[$i + 1]];
        }

        return $pairs;
    }

    /**
     * @return array<int, true> pozycje slotów z bye
     */
    private static function byePositions(int $bracketSize, int $byeCount): array
    {
        $positions = [];

        for ($i = 0; $i < $bracketSize && count($positions) < $byeCount; $i += 2) {
            $positions[$i] = true;
        }
        for ($i = 1; $i < $bracketSize && count($positions) < $byeCount; $i += 2) {
            $positions[$i] = true;
        }

        return $positions;
    }

    public static function nextPowerOfTwo(int $n): int
    {
        if ($n < 1) {
            return 1;
        }

        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }

        return $power;
    }
}
