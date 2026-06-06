<?php

namespace App\Support\Tournament;

use InvalidArgumentException;
use RuntimeException;

final class PlayoffFirstRoundPairing
{
    /**
     * Losuje pary pierwszej rundy bez zawodników z tej samej grupy.
     *
     * @param list<array{player_id: int, group_number: int}> $advancingPlayers
     * @return list<array{0: int, 1: int}>
     */
    public static function pair(array $advancingPlayers): array
    {
        $count = count($advancingPlayers);

        if ($count === 0) {
            return [];
        }

        if ($count % 2 !== 0) {
            throw new InvalidArgumentException('Liczba awansujących musi być parzysta.');
        }

        if ($count === 2) {
            $a = $advancingPlayers[0];
            $b = $advancingPlayers[1];

            if ($a['group_number'] === $b['group_number']) {
                throw new RuntimeException('Nie można utworzyć pary pierwszej rundy bez wspólnej grupy.');
            }

            return [[$a['player_id'], $b['player_id']]];
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $pool = $advancingPlayers;
            shuffle($pool);

            $pairs = self::tryGreedyPairing($pool);

            if ($pairs !== null) {
                return $pairs;
            }
        }

        $pairs = self::pairWithBacktracking($advancingPlayers);

        if ($pairs === null) {
            throw new RuntimeException('Nie udało się wylosować par pierwszej rundy bez rematchu grupowego.');
        }

        return $pairs;
    }

    /**
     * @param list<array{player_id: int, group_number: int}> $pool
     * @return list<array{0: int, 1: int}>|null
     */
    private static function tryGreedyPairing(array $pool): ?array
    {
        $pairs = [];

        while (count($pool) >= 2) {
            $first = array_shift($pool);
            $partnerIndex = null;

            foreach ($pool as $index => $candidate) {
                if ($candidate['group_number'] !== $first['group_number']) {
                    $partnerIndex = $index;
                    break;
                }
            }

            if ($partnerIndex === null) {
                return null;
            }

            $partner = $pool[$partnerIndex];
            array_splice($pool, $partnerIndex, 1);

            $pairs[] = [$first['player_id'], $partner['player_id']];
        }

        return $pairs;
    }

    /**
     * @param list<array{player_id: int, group_number: int}> $players
     * @return list<array{0: int, 1: int}>|null
     */
    private static function pairWithBacktracking(array $players): ?array
    {
        $remaining = $players;
        shuffle($remaining);

        return self::backtrack($remaining, []);
    }

    /**
     * @param list<array{player_id: int, group_number: int}> $remaining
     * @param list<array{0: int, 1: int}> $pairs
     * @return list<array{0: int, 1: int}>|null
     */
    private static function backtrack(array $remaining, array $pairs): ?array
    {
        if ($remaining === []) {
            return $pairs;
        }

        if (count($remaining) === 1) {
            return null;
        }

        $first = array_shift($remaining);

        $candidates = array_keys($remaining);
        shuffle($candidates);

        foreach ($candidates as $index) {
            $partner = $remaining[$index];

            if ($partner['group_number'] === $first['group_number']) {
                continue;
            }

            $nextRemaining = $remaining;
            array_splice($nextRemaining, $index, 1);

            $result = self::backtrack(
                $nextRemaining,
                [...$pairs, [$first['player_id'], $partner['player_id']]],
            );

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: int}> $pairs
     * @param array<int, int> $groupByPlayerId
     */
    public static function pairsSatisfyGroupConstraint(array $pairs, array $groupByPlayerId): bool
    {
        foreach ($pairs as [$player1Id, $player2Id]) {
            if (($groupByPlayerId[$player1Id] ?? null) === ($groupByPlayerId[$player2Id] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
