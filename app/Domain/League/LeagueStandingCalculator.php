<?php

namespace App\Domain\League;

/**
 * Tabela ligowa: zwycięstwa → różnica jednostek → bilans bezpośredni.
 * Sezon z remisami: punkty 2/1/0, potem różnica jednostek → bezpośredni.
 * Nierozstrzygnięte grupy dostają `needsTiebreak`.
 */
final class LeagueStandingCalculator
{
    /**
     * @param  list<int>  $playerIds
     * @param  list<array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string, walkoverType?: string}>  $games
     * @return list<LeagueStandingRow>
     */
    public static function calculate(array $playerIds, array $games, bool $usePoints = false): array
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));
        $stats = [];
        foreach ($playerIds as $id) {
            $stats[$id] = [
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'for' => 0,
                'against' => 0,
            ];
        }

        $countable = [];
        foreach ($games as $game) {
            if (($game['status'] ?? '') !== 'finished') {
                continue;
            }
            $p1 = (int) $game['player1Id'];
            $p2 = (int) $game['player2Id'];
            if (! isset($stats[$p1], $stats[$p2])) {
                continue;
            }
            $countable[] = $game;
            $s1 = (int) $game['player1Score'];
            $s2 = (int) $game['player2Score'];
            $winnerId = $game['winnerId'] !== null ? (int) $game['winnerId'] : null;
            $walkover = (string) ($game['walkoverType'] ?? 'none');

            $stats[$p1]['played']++;
            $stats[$p2]['played']++;
            $stats[$p1]['for'] += $s1;
            $stats[$p1]['against'] += $s2;
            $stats[$p2]['for'] += $s2;
            $stats[$p2]['against'] += $s1;

            if ($winnerId === $p1) {
                $stats[$p1]['wins']++;
                $stats[$p1]['points'] += 2;
                $stats[$p2]['losses']++;
            } elseif ($winnerId === $p2) {
                $stats[$p2]['wins']++;
                $stats[$p2]['points'] += 2;
                $stats[$p1]['losses']++;
            } elseif ($winnerId === null && $s1 === 0 && $s2 === 0) {
                $stats[$p1]['losses']++;
                $stats[$p2]['losses']++;
            } else {
                $stats[$p1]['draws']++;
                $stats[$p2]['draws']++;
                $stats[$p1]['points'] += 1;
                $stats[$p2]['points'] += 1;
            }
        }

        $rows = [];
        foreach ($playerIds as $id) {
            $row = $stats[$id];
            $rows[] = new LeagueStandingRow(
                playerId: $id,
                played: $row['played'],
                wins: $row['wins'],
                draws: $row['draws'],
                losses: $row['losses'],
                points: $row['points'],
                unitsFor: $row['for'],
                unitsAgainst: $row['against'],
                unitDiff: $row['for'] - $row['against'],
                place: 0,
            );
        }

        usort($rows, static function (LeagueStandingRow $a, LeagueStandingRow $b) use ($usePoints): int {
            if ($usePoints && $a->points !== $b->points) {
                return $b->points <=> $a->points;
            }
            if (! $usePoints && $a->wins !== $b->wins) {
                return $b->wins <=> $a->wins;
            }
            if ($a->unitDiff !== $b->unitDiff) {
                return $b->unitDiff <=> $a->unitDiff;
            }

            return $a->playerId <=> $b->playerId;
        });

        return self::assignPlaces($rows, $countable, $usePoints);
    }

    /**
     * @param  list<LeagueStandingRow>  $rows
     * @param  list<array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string, walkoverType?: string}>  $games
     * @return list<LeagueStandingRow>
     */
    private static function assignPlaces(array $rows, array $games, bool $usePoints = false): array
    {
        $placed = [];
        $index = 0;
        $n = count($rows);

        while ($index < $n) {
            $group = [$rows[$index]];
            $j = $index + 1;
            while ($j < $n && self::samePrimaryRecord($rows[$j], $rows[$index], $usePoints)) {
                $group[] = $rows[$j];
                $j++;
            }

            $ranked = self::rankByMiniTable($group, $games);
            $offset = 0;
            $g = 0;
            $groupIds = array_map(static fn (LeagueStandingRow $row) => $row->playerId, $group);
            while ($g < count($ranked)) {
                $sub = [$ranked[$g]];
                $h = $g + 1;
                while ($h < count($ranked) && self::sameMiniRecord($ranked[$g], $ranked[$h], $groupIds, $games)) {
                    $sub[] = $ranked[$h];
                    $h++;
                }
                $place = $index + $offset + 1;
                $tied = count($sub) > 1;
                $key = $tied ? self::tieGroupKey(array_map(fn (LeagueStandingRow $r) => $r->playerId, $sub)) : null;
                foreach ($sub as $row) {
                    $placed[] = $row->withPlace($place, $tied, $key);
                }
                $offset += count($sub);
                $g = $h;
            }
            $index = $j;
        }

        return $placed;
    }

    private static function samePrimaryRecord(LeagueStandingRow $a, LeagueStandingRow $b, bool $usePoints): bool
    {
        if ($a->unitDiff !== $b->unitDiff) {
            return false;
        }

        return $usePoints ? $a->points === $b->points : $a->wins === $b->wins;
    }

    /**
     * @param  list<LeagueStandingRow>  $group
     * @param  list<array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}>  $games
     * @return list<LeagueStandingRow>
     */
    private static function rankByMiniTable(array $group, array $games): array
    {
        if (count($group) <= 1) {
            return $group;
        }

        $ids = array_map(static fn (LeagueStandingRow $row) => $row->playerId, $group);
        usort($group, static function (LeagueStandingRow $a, LeagueStandingRow $b) use ($ids, $games): int {
            $sa = self::miniStats($a->playerId, $ids, $games);
            $sb = self::miniStats($b->playerId, $ids, $games);
            if ($sa['points'] !== $sb['points']) {
                return $sb['points'] <=> $sa['points'];
            }
            if ($sa['wins'] !== $sb['wins']) {
                return $sb['wins'] <=> $sa['wins'];
            }
            if ($sa['diff'] !== $sb['diff']) {
                return $sb['diff'] <=> $sa['diff'];
            }

            return $a->playerId <=> $b->playerId;
        });

        return $group;
    }

    /**
     * @param  list<int>  $groupIds
     * @param  list<array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}>  $games
     */
    private static function sameMiniRecord(LeagueStandingRow $a, LeagueStandingRow $b, array $groupIds, array $games): bool
    {
        return self::miniStats($a->playerId, $groupIds, $games) === self::miniStats($b->playerId, $groupIds, $games);
    }

    /**
     * @param  list<int>  $groupIds
     * @param  list<array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}>  $games
     * @return array{wins: int, points: int, diff: int}
     */
    private static function miniStats(int $playerId, array $groupIds, array $games): array
    {
        $wins = 0;
        $points = 0;
        $diff = 0;
        foreach ($games as $game) {
            $p1 = (int) $game['player1Id'];
            $p2 = (int) $game['player2Id'];
            if (! in_array($p1, $groupIds, true) || ! in_array($p2, $groupIds, true)) {
                continue;
            }
            if ($p1 !== $playerId && $p2 !== $playerId) {
                continue;
            }
            $s1 = (int) $game['player1Score'];
            $s2 = (int) $game['player2Score'];
            $winnerId = $game['winnerId'] !== null ? (int) $game['winnerId'] : null;
            $walkover = (string) ($game['walkoverType'] ?? 'none');
            if ($p1 === $playerId) {
                $diff += $s1 - $s2;
            } else {
                $diff += $s2 - $s1;
            }
            if ($winnerId === $playerId) {
                $wins++;
                $points += 2;
            } elseif ($winnerId === null && $s1 === 0 && $s2 === 0) {
                // WO obustronny — bez punktów
            } elseif ($winnerId === null) {
                $points += 1;
            }
        }

        return ['wins' => $wins, 'points' => $points, 'diff' => $diff];
    }

    /**
     * @param  list<int>  $playerIds
     */
    public static function tieGroupKey(array $playerIds): string
    {
        $ids = $playerIds;
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Losowanie deterministyczne (crc32 ze seedu sezonu) — tylko gdy sportowo się nie da.
     *
     * @param  list<LeagueStandingRow>  $rows
     * @return list<LeagueStandingRow>
     */
    public static function breakRemainingTiesWithLottery(array $rows, int $seed): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->tieGroupKey ?? ('solo-'.$row->playerId)][] = $row;
        }

        $ordered = [];
        foreach ($groups as $groupRows) {
            if (count($groupRows) === 1) {
                $ordered[] = $groupRows[0]->withPlace(0, false, null);
                continue;
            }
            usort($groupRows, static function (LeagueStandingRow $a, LeagueStandingRow $b) use ($seed): int {
                return self::lotteryScore($seed, $a->playerId) <=> self::lotteryScore($seed, $b->playerId);
            });
            foreach ($groupRows as $row) {
                $ordered[] = $row->withPlace(0, false, null);
            }
        }

        $placed = [];
        foreach ($ordered as $i => $row) {
            $placed[] = $row->withPlace($i + 1, false, null);
        }

        return $placed;
    }

    public static function lotteryScore(int $seed, int $playerId): int
    {
        return crc32($seed.'-'.$playerId) & 0x7fffffff;
    }
}
