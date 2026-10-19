<?php

namespace App\Support\GameScoring;

/**
 * Składa wizyty H2H w rundy obu zawodników (jak tabela na sędziowaniu).
 * visit_number w bazie zostaje naprzemienny; tu jest tylko prezentacja.
 */
final class H2hLegVisitRows
{
    /**
     * @param  iterable<int, object>  $visits
     * @return list<array{leftThrown: string, leftRemaining: string, darts: int, rightRemaining: string, rightThrown: string, leftBust: bool, rightBust: bool}>
     */
    public static function fromVisits(iterable $visits, int $leftPlayerId, int $rightPlayerId): array
    {
        $sorted = collect($visits)
            ->sortBy(fn (object $visit): array => [
                (int) ($visit->visit_number ?? 0),
                (int) ($visit->id ?? 0),
            ])
            ->values();

        $rounds = [];
        $current = ['left' => null, 'right' => null];

        $flush = function () use (&$rounds, &$current): void {
            if ($current['left'] !== null || $current['right'] !== null) {
                $rounds[] = $current;
                $current = ['left' => null, 'right' => null];
            }
        };

        foreach ($sorted as $visit) {
            $side = self::side($visit, $leftPlayerId, $rightPlayerId);
            if ($side === null) {
                continue;
            }
            if ($current[$side] !== null) {
                $flush();
            }
            $current[$side] = $visit;
        }
        $flush();

        $rows = [];
        $total = count($rounds);
        foreach (array_reverse($rounds) as $displayIndex => $round) {
            $roundFromStart = $total - $displayIndex;
            $rows[] = [
                'leftThrown' => self::thrown($round['left']),
                'leftRemaining' => self::remaining($round['left']),
                'darts' => $roundFromStart * 3,
                'rightRemaining' => self::remaining($round['right']),
                'rightThrown' => self::thrown($round['right']),
                'leftBust' => (bool) ($round['left']->bust ?? false),
                'rightBust' => (bool) ($round['right']->bust ?? false),
            ];
        }

        return $rows;
    }

    private static function side(object $visit, int $leftPlayerId, int $rightPlayerId): ?string
    {
        $playerId = (int) ($visit->player_id ?? 0);
        if ($playerId === $leftPlayerId) {
            return 'left';
        }
        if ($playerId === $rightPlayerId) {
            return 'right';
        }

        return null;
    }

    private static function thrown(?object $visit): string
    {
        if ($visit === null) {
            return '-';
        }
        if ((bool) ($visit->bust ?? false)) {
            return 'Bust';
        }

        return (string) ($visit->score ?? 0);
    }

    private static function remaining(?object $visit): string
    {
        if ($visit === null) {
            return '-';
        }
        if ($visit->remaining_after !== null && $visit->remaining_after !== '') {
            return (string) $visit->remaining_after;
        }
        if ((bool) ($visit->bust ?? false) && $visit->remaining_before !== null && $visit->remaining_before !== '') {
            return (string) $visit->remaining_before;
        }

        return '-';
    }
}
