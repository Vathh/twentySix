<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\GameStatisticsCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class GameStatisticsCalculatorTest extends TestCase
{
    public function test_player_match_stats_counts_visit_score_bands_and_averages(): void
    {
        $leg1 = (object) ['id' => 1, 'finished_at' => now(), 'winner_id' => 10];
        $leg2 = (object) ['id' => 2, 'finished_at' => now(), 'winner_id' => 20];

        $visits = collect([
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 60, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 85, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 180, 'bust' => false, 'darts_in_visit' => 3, 'closed_leg' => true],
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 0, 'bust' => true, 'darts_in_visit' => 2],
            (object) ['game_leg_id' => 2, 'player_id' => 10, 'score' => 100, 'bust' => false, 'darts_in_visit' => 3],
        ]);

        // Stale stored averages must be ignored — source of truth is visits.
        $legStats = collect([
            (object) [
                'game_leg_id' => 1,
                'player_id' => 10,
                'leg_average' => 180.00,
                'darts_thrown' => 6,
            ],
            (object) [
                'game_leg_id' => 2,
                'player_id' => 10,
                'leg_average' => 50.00,
                'darts_thrown' => 3,
            ],
        ]);

        $stats = GameStatisticsCalculator::playerMatchStats(
            $visits,
            collect([$leg1, $leg2]),
            $legStats,
            10,
            null,
        );

        $this->assertSame(1, $stats['plus60']);
        $this->assertSame(1, $stats['plus80']);
        $this->assertSame(1, $stats['plus100']);
        $this->assertSame(0, $stats['plus140']);
        $this->assertSame(1, $stats['max180']);
        // leg2: 100; leg1: (325/11)*3 ≈ 88.64 — best is 100
        $this->assertSame(100.0, $stats['bestLegAverage']);
        $this->assertSame(11, $stats['bestLegThrows']);
        $this->assertNotNull($stats['matchAverage']);
    }

    public function test_best_leg_average_recalculates_nine_dart_finish_from_visits(): void
    {
        $leg = (object) ['id' => 1, 'finished_at' => now(), 'winner_id' => 10];
        $visits = collect([
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 180, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 180, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['game_leg_id' => 1, 'player_id' => 10, 'score' => 141, 'bust' => false, 'darts_in_visit' => 3, 'closed_leg' => true],
        ]);
        $staleStats = collect([
            (object) [
                'game_leg_id' => 1,
                'player_id' => 10,
                'leg_average' => 180.00,
                'darts_thrown' => 6,
            ],
        ]);

        $stats = GameStatisticsCalculator::playerMatchStats(
            $visits,
            collect([$leg]),
            $staleStats,
            10,
            null,
        );

        $this->assertSame(167.0, $stats['bestLegAverage']);
        $this->assertSame(167.0, $stats['matchAverage']);
        $this->assertSame(9, $stats['bestLegThrows']);
    }

    public function test_first_nine_average_equals_leg_average_when_fewer_than_nine_darts(): void
    {
        $visits = collect([
            (object) ['score' => 60, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['score' => 45, 'bust' => false, 'darts_in_visit' => 3],
        ]);

        $legAverage = GameStatisticsCalculator::legAverage($visits);
        $firstNine = GameStatisticsCalculator::firstNineAverage($visits);

        $this->assertSame(52.5, $legAverage);
        $this->assertSame($legAverage, $firstNine);
    }

    public function test_first_nine_average_uses_first_three_visits_when_nine_darts_thrown(): void
    {
        $visits = collect([
            (object) ['score' => 60, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['score' => 60, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['score' => 60, 'bust' => false, 'darts_in_visit' => 3],
            (object) ['score' => 100, 'bust' => false, 'darts_in_visit' => 3],
        ]);

        $this->assertSame(60.0, GameStatisticsCalculator::firstNineAverage($visits));
        $this->assertSame(70.0, GameStatisticsCalculator::legAverage($visits));
    }

    public function test_nine_dart_finish_includes_checkout_visit_in_leg_average(): void
    {
        $visits = collect([
            (object) ['score' => 180, 'bust' => false, 'darts_in_visit' => 3, 'closed_leg' => false],
            (object) ['score' => 180, 'bust' => false, 'darts_in_visit' => 3, 'closed_leg' => false],
            (object) ['score' => 141, 'bust' => false, 'darts_in_visit' => 3, 'closed_leg' => true],
        ]);

        // (180+180+141) / 9 * 3 = 167 — bez checkoutu byłoby 180
        $this->assertSame(167.0, GameStatisticsCalculator::legAverage($visits));
        $this->assertSame(167.0, GameStatisticsCalculator::firstNineAverage($visits));
        $this->assertSame(9, GameStatisticsCalculator::dartsThrown($visits));
        $this->assertSame(141, GameStatisticsCalculator::highestFinish($visits));
        $this->assertSame(180, GameStatisticsCalculator::highestVisit($visits));
    }
}
