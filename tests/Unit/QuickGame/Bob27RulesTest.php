<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\Bob27Rules;
use PHPUnit\Framework\TestCase;

class Bob27RulesTest extends TestCase
{
    public function test_perfect_score_is_1437_with_bull(): void
    {
        $this->assertSame(1437, Bob27Rules::perfectScore());
        $this->assertSame(1437, Bob27Rules::perfectScore(true));
    }

    public function test_perfect_score_without_bull_is_1287(): void
    {
        $this->assertSame(1287, Bob27Rules::perfectScore(false));
    }

    public function test_miss_subtracts_one_double_value(): void
    {
        $this->assertSame(25, Bob27Rules::applyVisit(27, 0, 0));
        $this->assertSame(15, Bob27Rules::applyVisit(27, 0, 5));
        $this->assertSame(-23, Bob27Rules::applyVisit(27, 0, 20));
    }

    public function test_hits_add_per_dart(): void
    {
        $this->assertSame(31, Bob27Rules::applyVisit(27, 2, 0));
        $this->assertSame(27 + 36, Bob27Rules::applyVisit(27, 3, 5));
        $this->assertSame(27 + 150, Bob27Rules::applyVisit(27, 3, 20));
    }

    public function test_hard_eliminates_at_zero_or_below(): void
    {
        $this->assertTrue(Bob27Rules::shouldEliminate(0, Bob27Rules::MODE_HARD));
        $this->assertTrue(Bob27Rules::shouldEliminate(-2, Bob27Rules::MODE_HARD));
        $this->assertFalse(Bob27Rules::shouldEliminate(1, Bob27Rules::MODE_HARD));
        $this->assertFalse(Bob27Rules::shouldEliminate(-10, Bob27Rules::MODE_EASY));
    }

    public function test_hard_last_survivor_wins(): void
    {
        $boards = [
            ['score' => -2, 'eliminated' => true],
            ['score' => 40, 'eliminated' => false],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_HARD,
            3,
            [1 => true],
        );
        $this->assertSame(Bob27Rules::KIND_WIN, $result['kind']);
        $this->assertSame(1, $result['winnerIndex']);
    }

    public function test_hard_solo_bust(): void
    {
        $boards = [
            ['score' => -2, 'eliminated' => true],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_HARD,
            0,
            [0 => true],
        );
        $this->assertSame(Bob27Rules::KIND_BUST, $result['kind']);
    }

    public function test_board_complete_highest_score_wins(): void
    {
        $boards = [
            ['score' => 80, 'eliminated' => false],
            ['score' => 40, 'eliminated' => false],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_EASY,
            Bob27Rules::LAST_TARGET_INDEX,
            [0 => true, 1 => true],
        );
        $this->assertSame(Bob27Rules::KIND_WIN, $result['kind']);
        $this->assertSame(0, $result['winnerIndex']);
    }

    public function test_board_complete_tie_resets(): void
    {
        $boards = [
            ['score' => 40, 'eliminated' => false],
            ['score' => 40, 'eliminated' => false],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_EASY,
            Bob27Rules::LAST_TARGET_INDEX,
            [0 => true, 1 => true],
        );
        $this->assertSame(Bob27Rules::KIND_TIE_RESET, $result['kind']);
    }

    public function test_board_complete_without_bull_after_d20(): void
    {
        $boards = [
            ['score' => 80, 'eliminated' => false],
            ['score' => 40, 'eliminated' => false],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_EASY,
            Bob27Rules::LAST_TARGET_INDEX_NO_BULL,
            [0 => true, 1 => true],
            [],
            false,
        );
        $this->assertSame(Bob27Rules::KIND_WIN, $result['kind']);
        $this->assertSame(0, $result['winnerIndex']);

        $continueOnD20WithBull = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_EASY,
            Bob27Rules::LAST_TARGET_INDEX_NO_BULL,
            [0 => true, 1 => true],
            [],
            true,
        );
        $this->assertSame(Bob27Rules::KIND_CONTINUE, $continueOnD20WithBull['kind']);
    }

    public function test_mid_board_waits_for_other_players(): void
    {
        $boards = [
            ['score' => 31, 'eliminated' => false],
            ['score' => 27, 'eliminated' => false],
        ];
        $result = Bob27Rules::resolveAfterCompletedVisit(
            $boards,
            Bob27Rules::MODE_EASY,
            0,
            [0 => true],
        );
        $this->assertSame(Bob27Rules::KIND_CONTINUE, $result['kind']);
    }

    public function test_target_labels_and_values(): void
    {
        $this->assertSame('D1', Bob27Rules::targetLabel(0));
        $this->assertSame(2, Bob27Rules::targetValue(0));
        $this->assertSame('D20', Bob27Rules::targetLabel(19));
        $this->assertSame(40, Bob27Rules::targetValue(19));
        $this->assertSame('Bull', Bob27Rules::targetLabel(20));
        $this->assertSame(50, Bob27Rules::targetValue(20));
        $this->assertSame('D20', Bob27Rules::targetLabel(19, false));
        $this->assertSame(40, Bob27Rules::targetValue(19, false));
        $this->assertSame(19, Bob27Rules::lastTargetIndex(false));
        $this->assertSame(20, Bob27Rules::lastTargetIndex(true));
    }
}
