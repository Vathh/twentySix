<?php

namespace App\Support\GameScoring;

use App\Models\Game\GameVisit;
use Illuminate\Support\Collection;

class GameStatisticsCalculator
{
    /**
     * @param  Collection<int, GameVisit>  $legVisits  wizyty jednego gracza w legu (aktywne)
     */
    public static function legAverage(Collection $legVisits): ?float
    {
        $darts = $legVisits->sum('darts_in_visit');
        if ($darts <= 0) {
            return null;
        }
        $points = $legVisits->where('bust', false)->sum('score');

        return round(($points / $darts) * 3, 2);
    }

    /**
     * Średnia z pierwszych trzech wizyt (9 lotek).
     * Gdy gracz nie zdążył rzucić 9 lotek (np. przeciwnik skończył lega wcześniej),
     * zwracamy średnią z całego lega — ta sama wartość co legAverage.
     *
     * @param  Collection<int, GameVisit>  $legVisits
     */
    public static function firstNineAverage(Collection $legVisits): ?float
    {
        if (self::dartsThrown($legVisits) < 9) {
            return self::legAverage($legVisits);
        }

        $firstThree = $legVisits->where('bust', false)->take(3);
        if ($firstThree->count() < 3) {
            return self::legAverage($legVisits);
        }
        $darts = $firstThree->sum('darts_in_visit');
        if ($darts <= 0) {
            return null;
        }
        $points = $firstThree->sum('score');

        return round(($points / $darts) * 3, 2);
    }

    /**
     * @param  Collection<int, GameVisit>  $legVisits
     */
    public static function highestVisit(Collection $legVisits): ?int
    {
        $max = $legVisits->where('bust', false)->max('score');

        return $max !== null ? (int) $max : null;
    }

    /**
     * @param  Collection<int, GameVisit>  $legVisits
     */
    public static function highestFinish(Collection $legVisits): ?int
    {
        $checkout = $legVisits->where('closed_leg', true)->where('bust', false)->first();

        return $checkout ? (int) $checkout->score : null;
    }

    /**
     * @param  Collection<int, GameVisit>  $legVisits
     */
    public static function dartsThrown(Collection $legVisits): int
    {
        return (int) $legVisits->sum('darts_in_visit');
    }

    /**
     * @param  Collection<int, GameVisit>  $legVisits
     */
    public static function checkoutDart(Collection $legVisits): ?int
    {
        $checkout = $legVisits->where('closed_leg', true)->where('bust', false)->first();

        return $checkout ? (int) $checkout->darts_in_visit : null;
    }

    /**
     * @param  Collection<int, GameVisit>  $gameVisits  wszystkie wizyty gracza w rozgrywce
     */
    public static function gameAverage(Collection $gameVisits): ?float
    {
        return self::legAverage($gameVisits);
    }

    /**
     * @param  Collection<int, GameVisit>  $legStats  kolekcja GameLegPlayerStat
     */
    public static function gameDoublePercent(Collection $legStats, int $playerId): ?float
    {
        $rows = $legStats->where('player_id', $playerId)->where('double_tracked', true);
        $attempts = $rows->sum('double_attempts');
        if ($attempts <= 0) {
            return null;
        }
        $successes = $rows->sum('double_successes');

        return round(($successes / $attempts) * 100, 1);
    }

    /**
     * Statystyki meczu gracza — te same metryki co zakładka „Statystyki” w mobile (product.md).
     *
     * @param  Collection<int, GameVisit>  $allVisits
     * @param  Collection<int, \App\Models\Game\GameLeg>  $legs
     * @param  Collection<int, \App\Models\Game\GameLegPlayerStat>  $legStats
     * @return array{
     *     matchAverage: ?float,
     *     bestLegAverage: ?float,
     *     currentLegAverage: ?float,
     *     bestLegThrows: ?int,
     *     plus60: int,
     *     plus80: int,
     *     plus100: int,
     *     plus140: int,
     *     max180: int,
     * }
     */
    public static function playerMatchStats(
        Collection $allVisits,
        Collection $legs,
        Collection $legStats,
        int $playerId,
        ?int $openLegId = null,
    ): array {
        $playerVisits = $allVisits->where('player_id', $playerId);
        $scoredVisits = $playerVisits->where('bust', false);
        /** @var Collection<int, int> $scores */
        $scores = $scoredVisits->map(fn ($v) => (int) $v->score);

        $playerLegStats = $legStats->where('player_id', $playerId);
        $finishedLegs = $legs->whereNotNull('finished_at');

        $legsAverages = $finishedLegs->map(function ($leg) use ($playerLegStats, $allVisits, $playerId) {
            $stat = $playerLegStats->firstWhere('game_leg_id', $leg->id);
            if ($stat?->leg_average !== null) {
                return (float) $stat->leg_average;
            }

            return self::legAverage(
                $allVisits->where('game_leg_id', $leg->id)->where('player_id', $playerId),
            );
        })->filter(fn ($v) => $v !== null);

        $bestLegAverage = $legsAverages->isNotEmpty()
            ? round((float) $legsAverages->max(), 2)
            : null;

        $dartsPerWonLeg = $finishedLegs
            ->where('winner_id', $playerId)
            ->map(function ($leg) use ($playerLegStats, $allVisits, $playerId) {
                $stat = $playerLegStats->firstWhere('game_leg_id', $leg->id);
                if ($stat?->darts_thrown !== null && $stat->darts_thrown > 0) {
                    return (int) $stat->darts_thrown;
                }

                $darts = (int) $allVisits
                    ->where('game_leg_id', $leg->id)
                    ->where('player_id', $playerId)
                    ->sum('darts_in_visit');

                return $darts > 0 ? $darts : null;
            })
            ->filter();

        $openLegVisits = $openLegId
            ? $playerVisits->where('game_leg_id', $openLegId)
            : collect();

        return [
            'matchAverage' => self::gameAverage($playerVisits),
            'bestLegAverage' => $bestLegAverage,
            'currentLegAverage' => $openLegId ? self::legAverage($openLegVisits) : null,
            'bestLegThrows' => $dartsPerWonLeg->isNotEmpty() ? (int) $dartsPerWonLeg->min() : null,
            'plus60' => self::countVisitScoresInRange($scores, 60, 80),
            'plus80' => self::countVisitScoresInRange($scores, 80, 100),
            'plus100' => self::countVisitScoresInRange($scores, 100, 140),
            'plus140' => self::countVisitScoresInRange($scores, 140, 180),
            'max180' => self::countVisitScoresInRange($scores, 180, 181),
        ];
    }

    /**
     * @param  Collection<int, int>  $scores
     */
    private static function countVisitScoresInRange(Collection $scores, int $min, int $maxExclusive): int
    {
        return $scores->filter(fn (int $score) => $score >= $min && $score < $maxExclusive)->count();
    }
}
