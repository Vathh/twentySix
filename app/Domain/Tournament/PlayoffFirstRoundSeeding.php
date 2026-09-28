<?php

namespace App\Domain\Tournament;

use InvalidArgumentException;
use RuntimeException;

/**
 * Rozstawienie pierwszej rundy po fazie grupowej.
 *
 * Równy awans (każda grupa tyle samo miejsc K, parzysta liczba grup): sąsiednie grupy
 * (1–2, 3–4, …), miejsce r gra z miejscem K+1−r sąsiada. Lustrzane mecze idą do
 * przeciwnych połówek drabinki. Dla K = 2 to szablon mundialu (1A–2B, potem 1B–2A).
 *
 * Nierówny awans: kolejny najlepszy dostaje najsłabszego rywala z innej grupy.
 */
final class PlayoffFirstRoundSeeding
{
    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $advancingPlayers
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

        $byGroup = [];

        foreach ($advancingPlayers as $player) {
            $byGroup[$player['group_number']][] = $player;
        }

        $groupNumbers = array_keys($byGroup);
        sort($groupNumbers);

        $perGroup = count($byGroup[$groupNumbers[0]]);
        $uniform = count($groupNumbers) % 2 === 0
            && self::everyGroupHasCount($byGroup, $perGroup)
            && ($perGroup === 1 || $perGroup % 2 === 0);

        if ($uniform) {
            return self::pairUniform($byGroup, $groupNumbers, $perGroup);
        }

        $matches = self::pairGreedy($advancingPlayers);

        if ($matches === null) {
            throw new RuntimeException('Nie można utworzyć par pierwszej rundy bez wspólnej grupy.');
        }

        return self::assignUnevenSlots($matches);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     * @param  array<int, int>  $groupByPlayerId
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

    /**
     * @param  array<int, list<array{player_id: int, group_number: int, place: int}>>  $byGroup
     */
    private static function everyGroupHasCount(array $byGroup, int $count): bool
    {
        foreach ($byGroup as $players) {
            if (count($players) !== $count) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, list<array{player_id: int, group_number: int, place: int}>>  $byGroup
     * @param  list<int>  $groupNumbers
     * @return list<array{0: int, 1: int}>
     */
    private static function pairUniform(array $byGroup, array $groupNumbers, int $perGroup): array
    {
        foreach ($byGroup as $groupNumber => $players) {
            usort(
                $players,
                static fn (array $a, array $b): int => $a['place'] <=> $b['place'] ?: $a['player_id'] <=> $b['player_id'],
            );
            $byGroup[$groupNumber] = $players;
        }

        if ($perGroup === 1) {
            $pairs = [];

            for ($index = 0; $index < count($groupNumbers); $index += 2) {
                $pairs[] = self::orderedIds(
                    $byGroup[$groupNumbers[$index]][0],
                    $byGroup[$groupNumbers[$index + 1]][0],
                );
            }

            return $pairs;
        }

        $bundleCount = intdiv(count($groupNumbers), 2);
        $uppers = [];
        $lowers = [];

        for ($rank = 1; $rank <= intdiv($perGroup, 2); $rank++) {
            for ($bundle = 0; $bundle < $bundleCount; $bundle++) {
                $groupA = $groupNumbers[$bundle * 2];
                $groupB = $groupNumbers[$bundle * 2 + 1];
                $uppers[] = self::orderedIds(
                    $byGroup[$groupA][$rank - 1],
                    $byGroup[$groupB][$perGroup - $rank],
                );
                $lowers[] = self::orderedIds(
                    $byGroup[$groupB][$rank - 1],
                    $byGroup[$groupA][$perGroup - $rank],
                );
            }
        }

        if ($bundleCount === 1 && $perGroup > 2) {
            return self::interleaveSingleBundle($uppers, $lowers);
        }

        return array_merge($uppers, $lowers);
    }

    /**
     * Dwie grupy i K > 2: każdy mecz i tak łączy te same grupy, więc „górne” mecze
     * nie mogą wypełnić pierwszej połówki obok siebie. 1. i 2. miejsce rozchodzą
     * się na przeciwne połówki.
     *
     * @param  list<array{0: int, 1: int}>  $uppers
     * @param  list<array{0: int, 1: int}>  $lowers
     * @return list<array{0: int, 1: int}>
     */
    private static function interleaveSingleBundle(array $uppers, array $lowers): array
    {
        $first = [];
        $second = [];

        foreach ($uppers as $index => $upper) {
            if ($index % 2 === 0) {
                $first[] = $upper;
                $second[] = $lowers[$index];
            } else {
                $first[] = $lowers[$index];
                $second[] = $upper;
            }
        }

        return array_merge($first, $second);
    }

    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $players
     * @return list<array{0: array{player_id: int, group_number: int, place: int}, 1: array{player_id: int, group_number: int, place: int}>}|null
     */
    private static function pairGreedy(array $players): ?array
    {
        $remaining = $players;
        usort(
            $remaining,
            static fn (array $a, array $b): int => $a['place'] <=> $b['place']
                ?: $a['group_number'] <=> $b['group_number']
                ?: $a['player_id'] <=> $b['player_id'],
        );

        $matches = [];

        while ($remaining !== []) {
            $best = array_shift($remaining);
            $opponentIndex = self::weakestOpponentIndex($remaining, $best['group_number']);

            if ($opponentIndex === null) {
                return null;
            }

            $opponent = $remaining[$opponentIndex];
            array_splice($remaining, $opponentIndex, 1);
            $matches[] = self::orderedPlayers($best, $opponent);
        }

        return $matches;
    }

    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $remaining
     */
    private static function weakestOpponentIndex(array $remaining, int $groupNumber): ?int
    {
        $bestIndex = null;

        foreach ($remaining as $index => $candidate) {
            if ($candidate['group_number'] === $groupNumber) {
                continue;
            }

            if ($bestIndex === null || self::isPreferredOpponent($candidate, $remaining[$bestIndex], $groupNumber)) {
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * @param  array{player_id: int, group_number: int, place: int}  $candidate
     * @param  array{player_id: int, group_number: int, place: int}  $current
     */
    private static function isPreferredOpponent(array $candidate, array $current, int $groupNumber): bool
    {
        if ($candidate['place'] !== $current['place']) {
            return $candidate['place'] > $current['place'];
        }

        $candidateAdjacent = abs($candidate['group_number'] - $groupNumber) === 1;
        $currentAdjacent = abs($current['group_number'] - $groupNumber) === 1;

        if ($candidateAdjacent !== $currentAdjacent) {
            return $candidateAdjacent;
        }

        if ($candidate['group_number'] !== $current['group_number']) {
            return $candidate['group_number'] < $current['group_number'];
        }

        return $candidate['player_id'] < $current['player_id'];
    }

    /**
     * Mecze dzielące grupę rozkłada na przeciwne połówki, gdy pierwsza runda ma co najmniej 4 mecze.
     *
     * @param  list<array{0: array{player_id: int, group_number: int, place: int}, 1: array{player_id: int, group_number: int, place: int}>}  $matches
     * @return list<array{0: int, 1: int}>
     */
    private static function assignUnevenSlots(array $matches): array
    {
        usort(
            $matches,
            static function (array $left, array $right): int {
                return $left[0]['place'] <=> $right[0]['place']
                    ?: $left[0]['group_number'] <=> $right[0]['group_number']
                    ?: $left[1]['place'] <=> $right[1]['place']
                    ?: $left[1]['group_number'] <=> $right[1]['group_number']
                    ?: $left[0]['player_id'] <=> $right[0]['player_id'];
            },
        );

        if (count($matches) < 4) {
            return array_map(
                static fn (array $match): array => [$match[0]['player_id'], $match[1]['player_id']],
                $matches,
            );
        }

        $half = intdiv(count($matches), 2);
        $halves = [[], []];
        $groupCounts = [[], []];

        foreach ($matches as $match) {
            $groups = [$match[0]['group_number'], $match[1]['group_number']];
            $chosen = null;
            $bestKey = null;

            foreach ([0, 1] as $side) {
                if (count($halves[$side]) >= $half) {
                    continue;
                }

                $score = 0;

                foreach ($groups as $group) {
                    $score += $groupCounts[$side][$group] ?? 0;
                }

                $capacityLeft = $half - count($halves[$side]);
                $key = [$score, -$capacityLeft, $side];

                if ($bestKey === null || $key < $bestKey) {
                    $bestKey = $key;
                    $chosen = $side;
                }
            }

            $halves[$chosen][] = [$match[0]['player_id'], $match[1]['player_id']];

            foreach ($groups as $group) {
                $groupCounts[$chosen][$group] = ($groupCounts[$chosen][$group] ?? 0) + 1;
            }
        }

        return array_merge($halves[0], $halves[1]);
    }

    /**
     * @param  array{player_id: int, group_number: int, place: int}  $a
     * @param  array{player_id: int, group_number: int, place: int}  $b
     * @return array{0: int, 1: int}
     */
    private static function orderedIds(array $a, array $b): array
    {
        [$first, $second] = self::orderedPlayers($a, $b);

        return [$first['player_id'], $second['player_id']];
    }

    /**
     * @param  array{player_id: int, group_number: int, place: int}  $a
     * @param  array{player_id: int, group_number: int, place: int}  $b
     * @return array{0: array{player_id: int, group_number: int, place: int}, 1: array{player_id: int, group_number: int, place: int}}
     */
    private static function orderedPlayers(array $a, array $b): array
    {
        if (self::isBetter($a, $b)) {
            return [$a, $b];
        }

        return [$b, $a];
    }

    /**
     * @param  array{player_id: int, group_number: int, place: int}  $a
     * @param  array{player_id: int, group_number: int, place: int}  $b
     */
    private static function isBetter(array $a, array $b): bool
    {
        if ($a['place'] !== $b['place']) {
            return $a['place'] < $b['place'];
        }

        if ($a['group_number'] !== $b['group_number']) {
            return $a['group_number'] < $b['group_number'];
        }

        return $a['player_id'] <= $b['player_id'];
    }
}
