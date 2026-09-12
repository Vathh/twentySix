<?php

namespace App\Support\Tournament;

use App\Domain\Tournament\TournamentStartRules;

/**
 * Losowe pary R1: gracze i BYE w jednej puli (bez rozkładania bye).
 * Dwa BYE w jednej parze są dozwolone — BYE awansuje do następnej rundy.
 */
final class PlayoffByePairing
{
    /**
     * @param  list<int>  $playerIds
     * @return list<array{0: int, 1: int}>
     */
    public static function pair(array $playerIds, int $bracketSize, int $byePlayerId): array
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

        $byeCount = $bracketSize - $playerCount;
        $pool = array_merge(array_values($playerIds), array_fill(0, $byeCount, $byePlayerId));
        shuffle($pool);

        return self::chunkPairs($pool);
    }

    /**
     * @param  list<int>  $orderedPool
     * @return list<array{0: int, 1: int}>
     */
    public static function chunkPairs(array $orderedPool): array
    {
        $count = count($orderedPool);
        if ($count === 0 || $count % 2 !== 0) {
            throw new \InvalidArgumentException('Pula slotów musi mieć parzystą długość.');
        }

        $pairs = [];
        for ($i = 0; $i < $count; $i += 2) {
            $pairs[] = [$orderedPool[$i], $orderedPool[$i + 1]];
        }

        return $pairs;
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
