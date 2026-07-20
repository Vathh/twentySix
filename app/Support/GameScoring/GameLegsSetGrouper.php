<?php

namespace App\Support\GameScoring;

use Illuminate\Support\Collection;

/**
 * Grupuje bloki legów (z GameDetailService) w sety na podstawie formatu meczu.
 */
final class GameLegsSetGrouper
{
    /**
     * @param  Collection<int, array{leg: object, visits: mixed, playerStats: mixed}>  $legsDetail
     * @return list<array{setNumber: int, legs: list<array{leg: object, visits: mixed, playerStats: mixed, legInSetNumber: int}>}>
     */
    public static function group(
        Collection $legsDetail,
        MatchFormat $format,
        int $player1Id,
        int $player2Id,
    ): array {
        $ordered = $legsDetail
            ->sortBy(fn (array $block) => (int) $block['leg']->leg_number)
            ->values();

        if ($ordered->isEmpty()) {
            return [];
        }

        if ($format->isSingleSet()) {
            return [[
                'setNumber' => 1,
                'legs' => self::numberLegsInSet($ordered->all()),
            ]];
        }

        $legsToWinSet = max(1, $format->legsToWinSet);
        $sets = [];
        $current = [];
        $p1Wins = 0;
        $p2Wins = 0;

        foreach ($ordered as $block) {
            $current[] = $block;
            $winnerId = $block['leg']->winner_id;

            if ($winnerId !== null && $block['leg']->finished_at !== null) {
                if ((int) $winnerId === $player1Id) {
                    $p1Wins++;
                } elseif ((int) $winnerId === $player2Id) {
                    $p2Wins++;
                }
            }

            if ($p1Wins >= $legsToWinSet || $p2Wins >= $legsToWinSet) {
                $sets[] = [
                    'setNumber' => count($sets) + 1,
                    'legs' => self::numberLegsInSet($current),
                ];
                $current = [];
                $p1Wins = 0;
                $p2Wins = 0;
            }
        }

        if ($current !== []) {
            $sets[] = [
                'setNumber' => count($sets) + 1,
                'legs' => self::numberLegsInSet($current),
            ];
        }

        return $sets;
    }

    /**
     * @param  list<array{leg: object, visits: mixed, playerStats: mixed}>  $legs
     * @return list<array{leg: object, visits: mixed, playerStats: mixed, legInSetNumber: int}>
     */
    private static function numberLegsInSet(array $legs): array
    {
        $numbered = [];
        foreach (array_values($legs) as $index => $block) {
            $numbered[] = array_merge($block, ['legInSetNumber' => $index + 1]);
        }

        return $numbered;
    }
}
