<?php

namespace App\Domain\Tournament;

use InvalidArgumentException;

/**
 * Drabinka pocieszenia: ta sama reguła miejsc co drabinka główna.
 * Wolny los staje naprzeciw zawodnika. Dwa wolne losy w jednej parze
 * tylko wtedy, gdy wolnych losów jest więcej niż zawodników.
 */
final class PlayoffConsolationSeeding
{
    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $players
     * @return list<array{0: int, 1: int}>
     */
    public static function pair(array $players, int $bracketSize, int $byePlayerId): array
    {
        $count = count($players);
        if ($count < 2 || $count > $bracketSize) {
            throw new InvalidArgumentException(
                "Liczba zawodników pocieszenia ({$count}) musi być w zakresie 2–{$bracketSize}.",
            );
        }

        if ($count === $bracketSize) {
            return PlayoffFirstRoundSeeding::pair($players);
        }

        $ordered = $players;
        usort(
            $ordered,
            static fn (array $a, array $b): int => $a['place'] <=> $b['place']
                ?: $a['group_number'] <=> $b['group_number']
                ?: $a['player_id'] <=> $b['player_id'],
        );

        $byeCount = $bracketSize - $count;
        $absorb = min($byeCount, $count);
        $pairs = [];

        for ($i = 0; $i < $absorb; $i++) {
            $pairs[] = [(int) $ordered[$i]['player_id'], $byePlayerId];
        }

        $rest = array_slice($ordered, $absorb);
        if ($rest !== []) {
            $pairs = array_merge($pairs, PlayoffFirstRoundSeeding::pair($rest));
        }

        $extraByes = $byeCount - $absorb;
        if ($extraByes > 0) {
            foreach (array_chunk(array_fill(0, $extraByes, $byePlayerId), 2) as $byePair) {
                $pairs[] = [$byePair[0], $byePair[1]];
            }
        }

        return $pairs;
    }
}
