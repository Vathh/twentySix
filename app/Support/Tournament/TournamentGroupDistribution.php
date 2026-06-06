<?php

namespace App\Support\Tournament;

use InvalidArgumentException;

final class TournamentGroupDistribution
{
    /**
     * Losuje zawodników i dzieli do grup od nr 1 w górę.
     * Większe grupy mają niższe numery (wcześniejsze), mniejsze — wyższe.
     *
     * @param list<int> $playerIds
     * @return list<list<int>>
     */
    public static function distribute(array $playerIds, int $groupsCount): array
    {
        if ($groupsCount < 1) {
            throw new InvalidArgumentException('Liczba grup musi być co najmniej 1.');
        }

        $playerIds = array_values($playerIds);
        shuffle($playerIds);

        $sizes = self::groupSizes(count($playerIds), $groupsCount);
        $result = array_fill(0, $groupsCount, []);

        $offset = 0;

        foreach ($sizes as $groupIndex => $size) {
            if ($size === 0) {
                continue;
            }

            $result[$groupIndex] = array_slice($playerIds, $offset, $size);
            $offset += $size;
        }

        return $result;
    }

    /**
     * Rozmiary grup bez losowania (grupa 1 = indeks 0).
     *
     * @return list<int>
     */
    public static function groupSizes(int $playerCount, int $groupsCount): array
    {
        if ($groupsCount < 1) {
            throw new InvalidArgumentException('Liczba grup musi być co najmniej 1.');
        }

        if ($playerCount < 0) {
            throw new InvalidArgumentException('Liczba zawodników nie może być ujemna.');
        }

        $baseSize = intdiv($playerCount, $groupsCount);
        $remainder = $playerCount % $groupsCount;

        $sizes = [];

        for ($i = 0; $i < $groupsCount; $i++) {
            $sizes[] = $baseSize + ($i < $remainder ? 1 : 0);
        }

        return $sizes;
    }
}
