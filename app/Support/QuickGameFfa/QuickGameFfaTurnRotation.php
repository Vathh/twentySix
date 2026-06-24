<?php

namespace App\Support\QuickGameFfa;

/**
 * Rotacja tur FFA z pominięciem graczy, którzy świadomie opuścili mecz (status left).
 */
final class QuickGameFfaTurnRotation
{
    /**
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $leftPlayerIds
     * @return array<int, int>
     */
    public static function activePlayerIds(array $playerIds, array $leftPlayerIds): array
    {
        $left = array_flip($leftPlayerIds);

        return array_values(array_filter(
            $playerIds,
            static fn (int $id): bool => ! isset($left[$id]),
        ));
    }

    /**
     * Następny aktywny indeks po $fromIndex (nie włącznie).
     *
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $leftPlayerIds
     */
    public static function nextIndexAfter(int $fromIndex, array $playerIds, array $leftPlayerIds): int
    {
        $n = count($playerIds);
        if ($n === 0) {
            return 0;
        }

        for ($step = 1; $step <= $n; $step++) {
            $candidate = ($fromIndex + $step) % $n;
            $playerId = (int) $playerIds[$candidate];
            if (! in_array($playerId, $leftPlayerIds, true)) {
                return $candidate;
            }
        }

        return $fromIndex;
    }

    /**
     * Jeśli $index wskazuje gracza, który opuścił mecz — przeskocz do następnego aktywnego.
     *
     * @param  array<int, int>  $playerIds
     * @param  array<int, int>  $leftPlayerIds
     */
    public static function normalizeIndexAt(int $index, array $playerIds, array $leftPlayerIds): int
    {
        $n = count($playerIds);
        if ($n === 0 || $leftPlayerIds === []) {
            return $index;
        }

        $playerId = (int) ($playerIds[$index] ?? $playerIds[0]);
        if (! in_array($playerId, $leftPlayerIds, true)) {
            return $index;
        }

        return self::nextIndexAfter($index, $playerIds, $leftPlayerIds);
    }
}
