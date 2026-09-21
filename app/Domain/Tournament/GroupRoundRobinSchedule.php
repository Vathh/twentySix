<?php

namespace App\Domain\Tournament;

/**
 * Kolejność meczów grupowych na jedną tarczę i sędzia z grupy.
 *
 * Metoda koła układa rundy, w których nikt nie gra dwa razy, a potem układa
 * je jedna po drugiej tak, żeby na granicy rund powtórka zawodnika była jak najmniejsza.
 * Sędziego dobiera spośród osób, które w danym meczu nie grają, z obciążeniem
 * różniącym się co najwyżej o jeden mecz.
 */
final class GroupRoundRobinSchedule
{
    /**
     * @param  list<int>  $playerIds  kolejność losowania w grupie
     * @return list<array{player1_id: int, player2_id: int, sequence: int, referee_player_id: int}>
     */
    public static function build(array $playerIds): array
    {
        $playerIds = array_values(array_map(intval(...), $playerIds));
        $count = count($playerIds);

        if ($count < 2) {
            return [];
        }

        $indexOf = array_flip($playerIds);
        $rounds = self::rounds($playerIds, $indexOf);
        $matches = self::orderRounds($rounds);

        $referees = self::assignReferees($matches, $playerIds, $indexOf);

        $schedule = [];
        foreach ($matches as $index => $match) {
            $schedule[] = [
                'player1_id' => $match['player1_id'],
                'player2_id' => $match['player2_id'],
                'sequence' => $index + 1,
                'referee_player_id' => $referees[$index],
            ];
        }

        return $schedule;
    }

    /**
     * @param  list<int>  $playerIds
     * @param  array<int, int>  $indexOf
     * @return list<list<array{player1_id: int, player2_id: int, key: string}>>
     */
    private static function rounds(array $playerIds, array $indexOf): array
    {
        $circle = $playerIds;
        if (count($circle) % 2 === 1) {
            $circle[] = null;
        }

        $width = count($circle);
        $fixed = $circle[0];
        $rotating = array_slice($circle, 1);
        $rounds = [];

        for ($round = 0; $round < $width - 1; $round++) {
            $row = array_merge([$fixed], $rotating);
            $matches = [];

            for ($seat = 0; $seat < intdiv($width, 2); $seat++) {
                $left = $row[$seat];
                $right = $row[$width - 1 - $seat];
                if ($left === null || $right === null) {
                    continue;
                }
                $matches[] = self::orderedPair($left, $right, $indexOf);
            }

            $rounds[] = $matches;
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        return $rounds;
    }

    /**
     * @param  array<int, int>  $indexOf
     * @return array{player1_id: int, player2_id: int, key: string}
     */
    private static function orderedPair(int $left, int $right, array $indexOf): array
    {
        if ($indexOf[$left] > $indexOf[$right]) {
            [$left, $right] = [$right, $left];
        }

        $low = min($indexOf[$left], $indexOf[$right]);
        $high = max($indexOf[$left], $indexOf[$right]);

        return [
            'player1_id' => $left,
            'player2_id' => $right,
            'key' => sprintf('%04d-%04d', $low, $high),
        ];
    }

    /**
     * @param  list<list<array{player1_id: int, player2_id: int, key: string}>>  $rounds
     * @return list<array{player1_id: int, player2_id: int, key: string}>
     */
    private static function orderRounds(array $rounds): array
    {
        $ordered = [];
        /** @var list<int>|null $previous */
        $previous = null;

        foreach ($rounds as $round) {
            $remaining = array_values($round);

            while ($remaining !== []) {
                $pick = 0;
                $bestOverlap = PHP_INT_MAX;
                $bestKey = null;

                foreach ($remaining as $index => $match) {
                    $players = [$match['player1_id'], $match['player2_id']];
                    $overlap = $previous === null
                        ? 0
                        : count(array_intersect($previous, $players));

                    if (
                        $overlap < $bestOverlap
                        || ($overlap === $bestOverlap && ($bestKey === null || $match['key'] < $bestKey))
                    ) {
                        $pick = $index;
                        $bestOverlap = $overlap;
                        $bestKey = $match['key'];
                    }
                }

                $chosen = $remaining[$pick];
                array_splice($remaining, $pick, 1);
                $ordered[] = $chosen;
                $previous = [$chosen['player1_id'], $chosen['player2_id']];
            }
        }

        return $ordered;
    }

    /**
     * @param  list<array{player1_id: int, player2_id: int}>  $matches
     * @param  list<int>  $playerIds
     * @param  array<int, int>  $indexOf
     * @return list<int>
     */
    private static function assignReferees(array $matches, array $playerIds, array $indexOf): array
    {
        $counts = array_fill_keys($playerIds, 0);
        $referees = [];
        $total = count($matches);

        for ($index = 0; $index < $total; $index++) {
            $playing = [$matches[$index]['player1_id'], $matches[$index]['player2_id']];
            $nextPlaying = $index + 1 < $total
                ? [$matches[$index + 1]['player1_id'], $matches[$index + 1]['player2_id']]
                : [];
            $previousPlaying = $index > 0
                ? [$matches[$index - 1]['player1_id'], $matches[$index - 1]['player2_id']]
                : [];

            $candidates = array_values(array_filter(
                $playerIds,
                fn (int $id) => ! in_array($id, $playing, true),
            ));

            usort($candidates, function (int $left, int $right) use ($counts, $nextPlaying, $previousPlaying, $indexOf) {
                $byCount = $counts[$left] <=> $counts[$right];
                if ($byCount !== 0) {
                    return $byCount;
                }

                $leftNext = in_array($left, $nextPlaying, true) ? 1 : 0;
                $rightNext = in_array($right, $nextPlaying, true) ? 1 : 0;
                if ($leftNext !== $rightNext) {
                    return $leftNext <=> $rightNext;
                }

                $leftPrevious = in_array($left, $previousPlaying, true) ? 1 : 0;
                $rightPrevious = in_array($right, $previousPlaying, true) ? 1 : 0;
                if ($leftPrevious !== $rightPrevious) {
                    return $leftPrevious <=> $rightPrevious;
                }

                return $indexOf[$left] <=> $indexOf[$right];
            });

            $chosen = $candidates[0];
            $referees[$index] = $chosen;
            $counts[$chosen]++;
        }

        self::balanceReferees($matches, $referees, $counts);

        return $referees;
    }

    /**
     * @param  list<array{player1_id: int, player2_id: int}>  $matches
     * @param  list<int>  $referees
     * @param  array<int, int>  $counts
     */
    private static function balanceReferees(array $matches, array &$referees, array &$counts): void
    {
        if ($counts === []) {
            return;
        }

        $limit = count($matches) * count($counts);

        for ($step = 0; $step < $limit; $step++) {
            $min = min($counts);
            $max = max($counts);
            if ($max - $min <= 1) {
                return;
            }

            $swapped = false;

            foreach ($counts as $highId => $highCount) {
                if ($highCount !== $max) {
                    continue;
                }

                foreach ($counts as $lowId => $lowCount) {
                    if ($lowCount !== $min || $lowId === $highId) {
                        continue;
                    }

                    foreach ($referees as $index => $refereeId) {
                        if ($refereeId !== $highId) {
                            continue;
                        }

                        $playing = [$matches[$index]['player1_id'], $matches[$index]['player2_id']];
                        if (in_array($lowId, $playing, true)) {
                            continue;
                        }

                        $referees[$index] = $lowId;
                        $counts[$highId]--;
                        $counts[$lowId]++;
                        $swapped = true;
                        break 3;
                    }
                }
            }

            if (! $swapped) {
                return;
            }
        }
    }
}
