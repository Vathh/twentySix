<?php

namespace App\Domain\League;

/**
 * Awanse/spadki/baraże między szczeblami oraz łatanie dziur z dołu.
 */
final class LeaguePromotionResolver
{
    /**
     * @param  list<LeagueDivisionSnapshot>  $divisions  pozycja 0 = najwyższa
     * @param  array<int, list<LeagueStandingRow>>  $standingsByDivisionId
     * @param  list<array{higherDivisionId: int, lowerDivisionId: int, winnerId: int, loserId: int}>  $finishedPlayoffs
     * @return array{
     *     playoffPairings: list<LeaguePlayoffPairing>,
     *     rosterByDivisionId: array<int, list<int>>
     * }
     */
    public static function resolve(
        array $divisions,
        array $standingsByDivisionId,
        array $finishedPlayoffs = [],
    ): array {
        $divisions = array_values($divisions);
        usort($divisions, static fn (LeagueDivisionSnapshot $a, LeagueDivisionSnapshot $b) => $a->position <=> $b->position);

        $orderedIds = [];
        foreach ($divisions as $division) {
            $rows = $standingsByDivisionId[$division->id] ?? [];
            $active = array_values(array_filter(
                $rows,
                static fn (LeagueStandingRow $row) => in_array($row->playerId, $division->playerIds, true),
            ));
            usort($active, static fn (LeagueStandingRow $a, LeagueStandingRow $b) => $a->place <=> $b->place);
            $orderedIds[$division->id] = array_map(static fn (LeagueStandingRow $row) => $row->playerId, $active);
        }

        $playoffPairings = [];
        $autoUp = [];
        $autoDown = [];

        for ($i = 0; $i < count($divisions) - 1; $i++) {
            $higher = $divisions[$i];
            $lower = $divisions[$i + 1];
            $higherOrder = $orderedIds[$higher->id];
            $lowerOrder = $orderedIds[$lower->id];
            $direct = min($lower->promoteDirect, count($lowerOrder), count($higherOrder));
            $playoffCount = min($lower->promotePlayoff, max(0, count($lowerOrder) - $direct), max(0, count($higherOrder) - $direct));

            $promoted = array_slice($lowerOrder, 0, $direct);
            $relegated = $direct > 0 ? array_slice($higherOrder, -$direct) : [];
            foreach ($promoted as $playerId) {
                $autoUp[$playerId] = ['from' => $lower->id, 'to' => $higher->id];
            }
            foreach ($relegated as $playerId) {
                $autoDown[$playerId] = ['from' => $higher->id, 'to' => $lower->id];
            }

            $challengers = array_slice($lowerOrder, $direct, $playoffCount);
            $threatened = $playoffCount > 0 ? array_slice($higherOrder, -($direct + $playoffCount), $playoffCount) : [];
            $threatened = array_reverse($threatened);

            $count = min(count($challengers), count($threatened));
            for ($p = 0; $p < $count; $p++) {
                $playoffPairings[] = new LeaguePlayoffPairing(
                    higherDivisionId: $higher->id,
                    lowerDivisionId: $lower->id,
                    higherPlayerId: $threatened[$p],
                    lowerPlayerId: $challengers[$p],
                );
            }
        }

        $playoffsResolved = $finishedPlayoffs !== [] || $playoffPairings === [];
        if (! $playoffsResolved && $finishedPlayoffs === []) {
            $roster = [];
            foreach ($divisions as $division) {
                $roster[$division->id] = $orderedIds[$division->id];
            }

            return [
                'playoffPairings' => $playoffPairings,
                'rosterByDivisionId' => $roster,
            ];
        }

        $assignment = [];
        foreach ($divisions as $division) {
            foreach ($orderedIds[$division->id] as $playerId) {
                $assignment[$playerId] = $division->id;
            }
        }
        foreach ($autoUp as $playerId => $move) {
            $assignment[$playerId] = $move['to'];
        }
        foreach ($autoDown as $playerId => $move) {
            $assignment[$playerId] = $move['to'];
        }
        foreach ($finishedPlayoffs as $playoff) {
            $assignment[(int) $playoff['winnerId']] = (int) $playoff['higherDivisionId'];
            $assignment[(int) $playoff['loserId']] = (int) $playoff['lowerDivisionId'];
        }

        $roster = [];
        foreach ($divisions as $division) {
            $roster[$division->id] = [];
        }
        foreach ($assignment as $playerId => $divisionId) {
            $roster[$divisionId][] = $playerId;
        }

        $roster = self::fillVacancies($divisions, $roster, $orderedIds);

        return [
            'playoffPairings' => $playoffPairings,
            'rosterByDivisionId' => $roster,
        ];
    }

    /**
     * Dziury (poniżej limitu) łata kolejka z dołu. Spadków się nie anuluje.
     *
     * @param  list<LeagueDivisionSnapshot>  $divisions
     * @param  array<int, list<int>>  $roster
     * @param  array<int, list<int>>  $originalOrder
     * @return array<int, list<int>>
     */
    public static function fillVacancies(array $divisions, array $roster, array $originalOrder): array
    {
        $n = count($divisions);
        for ($i = 0; $i < $n - 1; $i++) {
            $higher = $divisions[$i];
            $need = $higher->capacity - count($roster[$higher->id]);
            if ($need <= 0) {
                continue;
            }

            for ($j = $i + 1; $j < $n && $need > 0; $j++) {
                $source = $divisions[$j];
                $candidates = $originalOrder[$source->id] ?? [];
                foreach ($candidates as $playerId) {
                    if ($need <= 0) {
                        break;
                    }
                    $currentIndex = array_search($playerId, $roster[$source->id], true);
                    if ($currentIndex === false) {
                        continue;
                    }
                    unset($roster[$source->id][$currentIndex]);
                    $roster[$source->id] = array_values($roster[$source->id]);
                    $roster[$higher->id][] = $playerId;
                    $need--;
                }
            }
        }

        return $roster;
    }

    /**
     * Czy grupa remisowa przecina linię awansu / barażu / spadku.
     *
     * @param  list<LeagueStandingRow>  $rows
     */
    public static function tieAffectsCut(array $rows, LeagueDivisionSnapshot $division, ?LeagueDivisionSnapshot $higher, ?LeagueDivisionSnapshot $lower): bool
    {
        $tied = array_values(array_filter($rows, static fn (LeagueStandingRow $row) => $row->needsTiebreak));
        if ($tied === []) {
            return false;
        }

        $groups = [];
        foreach ($tied as $row) {
            $groups[$row->tieGroupKey ?? ''][] = $row;
        }

        $count = count($rows);
        $autoUp = $higher !== null ? $division->promoteDirect : 0;
        $playoffUp = $higher !== null ? $division->promotePlayoff : 0;
        $autoDown = $lower !== null ? $lower->promoteDirect : 0;
        $playoffDown = $lower !== null ? $lower->promotePlayoff : 0;

        $bands = [];
        for ($place = 1; $place <= $count; $place++) {
            $band = 'mid';
            if ($autoUp > 0 && $place <= $autoUp) {
                $band = 'auto_up';
            } elseif ($playoffUp > 0 && $place <= $autoUp + $playoffUp) {
                $band = 'playoff_up';
            } elseif ($autoDown > 0 && $place > $count - $autoDown) {
                $band = 'auto_down';
            } elseif ($playoffDown > 0 && $place > $count - $autoDown - $playoffDown) {
                $band = 'playoff_down';
            }
            $bands[$place] = $band;
        }

        foreach ($groups as $group) {
            $span = count($group);
            $start = $group[0]->place;
            $groupBands = [];
            for ($p = $start; $p < $start + $span; $p++) {
                $groupBands[$bands[$p] ?? 'mid'] = true;
            }
            if (count($groupBands) > 1) {
                return true;
            }
        }

        return false;
    }
}
