<?php

namespace Tests\Unit\League;

use App\Domain\League\LeagueTieBreakBracket;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueTieBreakBracketTest extends TestCase
{
    #[Test]
    public function two_players_need_one_game_then_order_by_winner(): void
    {
        $pending = LeagueTieBreakBracket::next([10, 20], []);
        $this->assertNull($pending['ordered']);
        $this->assertCount(1, $pending['pending']);

        $done = LeagueTieBreakBracket::next([10, 20], [[
            'player1Id' => 10,
            'player2Id' => 20,
            'winnerId' => 20,
            'status' => 'finished',
            'bracketRound' => 1,
            'isThirdPlace' => false,
        ]]);
        $this->assertSame([20, 10], $done['ordered']);
    }

    #[Test]
    public function three_players_seed_one_gets_bye(): void
    {
        $first = LeagueTieBreakBracket::next([1, 2, 3], []);
        $this->assertSame(2, $first['pending'][0]['player1Id']);
        $this->assertSame(3, $first['pending'][0]['player2Id']);

        $afterSemi = LeagueTieBreakBracket::next([1, 2, 3], [[
            'player1Id' => 2,
            'player2Id' => 3,
            'winnerId' => 3,
            'status' => 'finished',
            'bracketRound' => 1,
            'isThirdPlace' => false,
        ]]);
        $this->assertSame(1, $afterSemi['pending'][0]['player1Id']);
        $this->assertSame(3, $afterSemi['pending'][0]['player2Id']);
    }
}
