<?php

namespace Tests\Unit\League;

use App\Domain\League\LeagueStandingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueStandingCalculatorTest extends TestCase
{
    #[Test]
    public function ranks_by_wins_then_unit_diff_then_head_to_head(): void
    {
        $rows = LeagueStandingCalculator::calculate(
            [1, 2, 3],
            [
                $this->finished(1, 2, 2, 0, 1),
                $this->finished(1, 3, 2, 1, 1),
                $this->finished(2, 3, 2, 0, 2),
            ],
        );

        $this->assertSame([1, 2, 3], array_map(fn ($row) => $row->playerId, $rows));
        $this->assertSame([1, 2, 3], array_map(fn ($row) => $row->place, $rows));
        $this->assertFalse($rows[0]->needsTiebreak);
    }

    #[Test]
    public function circular_results_mark_three_way_tie(): void
    {
        $rows = LeagueStandingCalculator::calculate(
            [1, 2, 3],
            [
                $this->finished(1, 2, 2, 1, 1),
                $this->finished(2, 3, 2, 1, 2),
                $this->finished(3, 1, 2, 1, 3),
            ],
        );

        $this->assertTrue($rows[0]->needsTiebreak);
        $this->assertTrue($rows[1]->needsTiebreak);
        $this->assertTrue($rows[2]->needsTiebreak);
        $this->assertSame($rows[0]->tieGroupKey, $rows[1]->tieGroupKey);
    }

    #[Test]
    public function double_walkover_counts_as_two_losses(): void
    {
        $rows = LeagueStandingCalculator::calculate(
            [1, 2],
            [[
                'player1Id' => 1,
                'player2Id' => 2,
                'player1Score' => 0,
                'player2Score' => 0,
                'winnerId' => null,
                'status' => 'finished',
            ]],
        );

        $this->assertSame(0, $rows[0]->wins);
        $this->assertSame(1, $rows[0]->losses);
        $this->assertSame(0, $rows[1]->wins);
        $this->assertSame(1, $rows[1]->losses);
    }

    #[Test]
    public function sporting_draw_awards_one_point_each_when_using_points(): void
    {
        $rows = LeagueStandingCalculator::calculate(
            [1, 2],
            [[
                'player1Id' => 1,
                'player2Id' => 2,
                'player1Score' => 3,
                'player2Score' => 3,
                'winnerId' => null,
                'status' => 'finished',
            ]],
            true,
        );

        $this->assertSame(1, $rows[0]->draws);
        $this->assertSame(1, $rows[1]->draws);
        $this->assertSame(1, $rows[0]->points);
        $this->assertSame(1, $rows[1]->points);
        $this->assertSame(0, $rows[0]->losses);
    }

    /**
     * @return array{player1Id: int, player2Id: int, player1Score: int, player2Score: int, winnerId: ?int, status: string}
     */
    private function finished(int $p1, int $p2, int $s1, int $s2, int $winner): array
    {
        return [
            'player1Id' => $p1,
            'player2Id' => $p2,
            'player1Score' => $s1,
            'player2Score' => $s2,
            'winnerId' => $winner,
            'status' => 'finished',
        ];
    }
}
