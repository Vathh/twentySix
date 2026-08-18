<?php

namespace Tests\Unit\League;

use App\Domain\League\RoundRobinScheduler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoundRobinSchedulerTest extends TestCase
{
    #[Test]
    public function four_players_produce_six_games_in_three_rounds(): void
    {
        $rounds = RoundRobinScheduler::rounds([1, 2, 3, 4], 1);

        $this->assertCount(3, $rounds);
        $pairs = [];
        foreach ($rounds as $round) {
            $this->assertCount(2, $round);
            foreach ($round as $pair) {
                $ids = [$pair['player1Id'], $pair['player2Id']];
                sort($ids);
                $pairs[] = implode('-', $ids);
            }
        }
        sort($pairs);
        $this->assertSame(['1-2', '1-3', '1-4', '2-3', '2-4', '3-4'], $pairs);
    }

    #[Test]
    public function odd_count_adds_bye_and_return_leg_swaps_sides(): void
    {
        $first = RoundRobinScheduler::rounds([1, 2, 3], 1);
        $this->assertCount(3, $first);
        foreach ($first as $round) {
            $this->assertCount(1, $round);
        }

        $double = RoundRobinScheduler::rounds([1, 2, 3], 2);
        $this->assertCount(6, $double);
        $this->assertSame($first[0][0]['player2Id'], $double[3][0]['player1Id']);
        $this->assertSame($first[0][0]['player1Id'], $double[3][0]['player2Id']);
    }
}
