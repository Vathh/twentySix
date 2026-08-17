<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\Catch40Rules;
use PHPUnit\Framework\TestCase;

class Catch40RulesTest extends TestCase
{
    public function test_points_table(): void
    {
        $this->assertSame(3, Catch40Rules::pointsForCheckout(61, 2));
        $this->assertSame(2, Catch40Rules::pointsForCheckout(61, 3));
        $this->assertSame(1, Catch40Rules::pointsForCheckout(61, 4));
        $this->assertSame(1, Catch40Rules::pointsForCheckout(61, 6));
        $this->assertSame(0, Catch40Rules::pointsForCheckout(61, 1));
        $this->assertSame(3, Catch40Rules::pointsForCheckout(99, 3));
        $this->assertSame(1, Catch40Rules::pointsForCheckout(99, 4));
    }

    public function test_checkout_advances_out_and_awards_points(): void
    {
        $board = Catch40Rules::emptyBoard();
        $next = Catch40Rules::applyVisit($board, 61, 0, 2, false, true);
        $this->assertSame(62, $next['outNumber']);
        $this->assertSame(62, $next['remaining']);
        $this->assertSame(0, $next['dartsUsed']);
        $this->assertSame(3, $next['catch40Score']);
        $this->assertFalse($next['finished']);
    }

    public function test_partial_visit_keeps_remaining(): void
    {
        $board = Catch40Rules::emptyBoard();
        $next = Catch40Rules::applyVisit($board, 20, 41, 3, false, false);
        $this->assertSame(61, $next['outNumber']);
        $this->assertSame(41, $next['remaining']);
        $this->assertSame(3, $next['dartsUsed']);
        $this->assertSame(0, $next['catch40Score']);
    }

    public function test_missing_or_zero_darts_counts_as_full_visit(): void
    {
        $board = Catch40Rules::emptyBoard();
        $next = Catch40Rules::applyVisit($board, 50, 11, 0, false, false);
        $this->assertSame(11, $next['remaining']);
        $this->assertSame(3, $next['dartsUsed']);
    }

    public function test_six_darts_without_checkout_is_a_miss(): void
    {
        $board = [
            'outNumber' => 61,
            'remaining' => 41,
            'dartsUsed' => 3,
            'catch40Score' => 0,
            'finished' => false,
        ];
        $next = Catch40Rules::applyVisit($board, 20, 21, 3, false, false);
        $this->assertSame(62, $next['outNumber']);
        $this->assertSame(0, $next['catch40Score']);
        $this->assertSame(0, $next['dartsUsed']);
    }

    public function test_finishing_100_marks_board_complete(): void
    {
        $board = [
            'outNumber' => 100,
            'remaining' => 40,
            'dartsUsed' => 0,
            'catch40Score' => 50,
            'finished' => false,
        ];
        $next = Catch40Rules::applyVisit($board, 40, 0, 2, false, true);
        $this->assertTrue($next['finished']);
        $this->assertSame(53, $next['catch40Score']);
    }

    public function test_all_finished_highest_score_wins(): void
    {
        $boards = [
            ['catch40Score' => 40, 'finished' => true],
            ['catch40Score' => 55, 'finished' => true],
        ];
        $result = Catch40Rules::resolveAfterVisit($boards);
        $this->assertSame(Catch40Rules::KIND_WIN, $result['kind']);
        $this->assertSame(1, $result['winnerIndex']);
    }

    public function test_tie_resets(): void
    {
        $boards = [
            ['catch40Score' => 40, 'finished' => true],
            ['catch40Score' => 40, 'finished' => true],
        ];
        $result = Catch40Rules::resolveAfterVisit($boards);
        $this->assertSame(Catch40Rules::KIND_TIE_RESET, $result['kind']);
    }

    public function test_waits_until_everyone_finishes(): void
    {
        $boards = [
            ['catch40Score' => 40, 'finished' => true],
            ['catch40Score' => 12, 'finished' => false],
        ];
        $result = Catch40Rules::resolveAfterVisit($boards);
        $this->assertSame(Catch40Rules::KIND_CONTINUE, $result['kind']);
    }
}
