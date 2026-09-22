<?php

namespace Tests\Unit\Stats;

use App\Domain\Stats\ThreeDartAverage;
use App\Domain\Stats\ThreeDartAverageSet;
use PHPUnit\Framework\TestCase;

class ThreeDartAverageTest extends TestCase
{
    public function test_average_is_points_per_three_darts(): void
    {
        $this->assertSame(100.0, ThreeDartAverage::fromTotals(100, 3));
        $this->assertNull(ThreeDartAverage::fromTotals(0, 0));
    }

    public function test_player_average_weights_by_darts_not_by_matches(): void
    {
        $set = ThreeDartAverageSet::fromRows([
            $this->row(ThreeDartAverageSet::KIND_GROUP, 1, 7, points: 100, darts: 3),
            $this->row(ThreeDartAverageSet::KIND_GROUP, 2, 7, points: 60, darts: 6),
        ]);

        $this->assertSame(100.0, $set->groupMatchAverage(1, 7));
        $this->assertSame(30.0, $set->groupMatchAverage(2, 7));
        $this->assertSame(53.33, $set->groupPlayerAverage(7));
    }

    public function test_bust_darts_count_and_bust_points_do_not(): void
    {
        $this->assertSame(30.0, ThreeDartAverage::fromTotals(60, 6));
    }

    public function test_tournament_average_adds_group_and_playoff_darts(): void
    {
        $set = ThreeDartAverageSet::fromRows([
            $this->row(ThreeDartAverageSet::KIND_GROUP, 1, 7, points: 100, darts: 3),
            $this->row(ThreeDartAverageSet::KIND_PLAYOFF, 9, 7, points: 50, darts: 3),
        ]);

        $this->assertSame(100.0, $set->groupPlayerAverage(7));
        $this->assertSame(50.0, $set->playoffMatchAverage(9, 7));
        $this->assertSame(75.0, $set->tournamentPlayerAverage(7));
    }

    public function test_missing_match_has_no_average(): void
    {
        $set = ThreeDartAverageSet::empty();

        $this->assertNull($set->groupMatchAverage(1, 7));
        $this->assertNull($set->leaguePlayerAverage(7));
    }

    /**
     * @return object{kind: string, match_id: int, player_id: int, points: int, darts: int}
     */
    private function row(string $kind, int $matchId, int $playerId, int $points, int $darts): object
    {
        return (object) [
            'kind' => $kind,
            'match_id' => $matchId,
            'player_id' => $playerId,
            'points' => $points,
            'darts' => $darts,
        ];
    }
}
