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

        $legStats = collect([
            (object) [
                'game_leg_id' => 1,
                'player_id' => 10,
                'leg_average' => 95.00,
                'darts_thrown' => 9,
            ],
            (object) [
                'game_leg_id' => 2,
                'player_id' => 10,
                'leg_average' => 100.00,
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
        $this->assertSame(100.00, $stats['bestLegAverage']);
        $this->assertSame(9, $stats['bestLegThrows']);
        $this->assertNotNull($stats['matchAverage']);
    }
}
