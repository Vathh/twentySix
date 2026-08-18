<?php

namespace App\Domain\League;

/**
 * Metoda kołowa (Berger): stały gracz na pozycji 0, rotacja reszty.
 * Nieparzyste N dostaje bye (brak meczu w danej kolejce).
 */
final class RoundRobinScheduler
{
    /**
     * @param  list<int>  $playerIds
     * @return list<list<array{player1Id: int, player2Id: int}>>
     */
    public static function rounds(array $playerIds, int $roundsEach = 1): array
    {
        $playerIds = array_values(array_unique($playerIds));

        if (count($playerIds) < 2) {
            return [];
        }

        if ($roundsEach !== 1 && $roundsEach !== 2) {
            throw new \InvalidArgumentException('Liczba spotkań każdy z każdym musi być 1 albo 2.');
        }

        $slots = $playerIds;
        if (count($slots) % 2 === 1) {
            $slots[] = null;
        }

        $n = count($slots);
        $roundCount = $n - 1;
        $half = (int) ($n / 2);
        $rounds = [];

        for ($r = 0; $r < $roundCount; $r++) {
            $pairs = [];
            for ($i = 0; $i < $half; $i++) {
                $a = $slots[$i];
                $b = $slots[$n - 1 - $i];
                if ($a === null || $b === null) {
                    continue;
                }
                if ($r % 2 === 1) {
                    [$a, $b] = [$b, $a];
                }
                $pairs[] = ['player1Id' => (int) $a, 'player2Id' => (int) $b];
            }
            $rounds[] = $pairs;

            $fixed = $slots[0];
            $rest = array_slice($slots, 1);
            $last = array_pop($rest);
            array_unshift($rest, $last);
            $slots = array_merge([$fixed], $rest);
        }

        if ($roundsEach === 2) {
            $returnLegs = [];
            foreach ($rounds as $pairs) {
                $swapped = [];
                foreach ($pairs as $pair) {
                    $swapped[] = [
                        'player1Id' => $pair['player2Id'],
                        'player2Id' => $pair['player1Id'],
                    ];
                }
                $returnLegs[] = $swapped;
            }
            $rounds = array_merge($rounds, $returnLegs);
        }

        return $rounds;
    }
}
