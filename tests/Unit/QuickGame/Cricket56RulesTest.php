<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\Cricket56Rules;
use PHPUnit\Framework\TestCase;

class Cricket56RulesTest extends TestCase
{
    public function test_perfect_score_is_60(): void
    {
        $score = 0;
        for ($round = 0; $round < 6; $round++) {
            $score = Cricket56Rules::applyVisit($score, 9, $round);
        }
        $score = Cricket56Rules::applyVisit($score, 6, 6);
        $this->assertSame(60, $score);
        $this->assertSame(60, Cricket56Rules::PERFECT_SCORE);
    }

    public function test_bull_caps_at_six(): void
    {
        $this->assertSame(6, Cricket56Rules::clampPoints(9, 6));
        $this->assertSame(2, Cricket56Rules::clampMark(3, 6));
        $this->assertSame('Bull', Cricket56Rules::targetLabel(6));
    }

    public function test_highest_score_wins_after_round_7(): void
    {
        $boards = [
            ['score' => 40],
            ['score' => 55],
        ];
        $result = Cricket56Rules::resolveAfterCompletedVisit($boards, 6, [0 => true, 1 => true]);
        $this->assertSame(Cricket56Rules::KIND_WIN, $result['kind']);
        $this->assertSame(1, $result['winnerIndex']);
    }

    public function test_tie_resets(): void
    {
        $boards = [
            ['score' => 40],
            ['score' => 40],
        ];
        $result = Cricket56Rules::resolveAfterCompletedVisit($boards, 6, [0 => true, 1 => true]);
        $this->assertSame(Cricket56Rules::KIND_TIE_RESET, $result['kind']);
    }

    public function test_waits_until_everyone_has_thrown(): void
    {
        $boards = [
            ['score' => 9],
            ['score' => 0],
        ];
        $result = Cricket56Rules::resolveAfterCompletedVisit($boards, 0, [0 => true]);
        $this->assertSame(Cricket56Rules::KIND_CONTINUE, $result['kind']);
    }
}
