<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\AroundTheClockRules;
use PHPUnit\Framework\TestCase;

class AroundTheClockRulesTest extends TestCase
{
    public function test_target_labels(): void
    {
        $this->assertSame('1', AroundTheClockRules::targetLabel(0));
        $this->assertSame('20', AroundTheClockRules::targetLabel(19));
        $this->assertSame('Bull', AroundTheClockRules::targetLabel(20));
        $this->assertSame('✓', AroundTheClockRules::targetLabel(21));
    }

    public function test_hits_advance_current_number(): void
    {
        $this->assertSame(
            ['targetIndex' => 1, 'finished' => false],
            AroundTheClockRules::applyVisit(0, 1),
        );
        $this->assertSame(
            ['targetIndex' => 3, 'finished' => false],
            AroundTheClockRules::applyVisit(0, 3),
        );
        $this->assertSame(
            ['targetIndex' => 0, 'finished' => false],
            AroundTheClockRules::applyVisit(0, 0),
        );
    }

    public function test_zero_hits_does_not_move(): void
    {
        $this->assertSame(
            ['targetIndex' => 7, 'finished' => false],
            AroundTheClockRules::applyVisit(7, 0),
        );
    }

    public function test_completing_on_bull_wins(): void
    {
        $this->assertSame(1, AroundTheClockRules::maxHits(20));
        $this->assertSame(
            ['targetIndex' => 21, 'finished' => true],
            AroundTheClockRules::applyVisit(20, 1),
        );
        $this->assertSame(
            ['targetIndex' => 21, 'finished' => true],
            AroundTheClockRules::applyVisit(20, 3),
        );
    }

    public function test_three_hits_from_nineteen_finishes(): void
    {
        $this->assertSame(
            ['targetIndex' => 21, 'finished' => true],
            AroundTheClockRules::applyVisit(18, 3),
        );
    }

    public function test_two_hits_from_nineteen_lands_on_bull(): void
    {
        $this->assertSame(
            ['targetIndex' => 20, 'finished' => false],
            AroundTheClockRules::applyVisit(18, 2),
        );
    }

    public function test_max_hits_near_the_end(): void
    {
        $this->assertSame(3, AroundTheClockRules::maxHits(0));
        $this->assertSame(3, AroundTheClockRules::maxHits(18));
        $this->assertSame(2, AroundTheClockRules::maxHits(19));
        $this->assertSame(1, AroundTheClockRules::maxHits(20));
        $this->assertSame(0, AroundTheClockRules::maxHits(21));
    }
}
