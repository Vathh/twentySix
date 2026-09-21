<?php

namespace Tests\Unit\GameScoring;

use App\Domain\GameScoring\DartLimitRules;
use DomainException;
use PHPUnit\Framework\TestCase;

class DartLimitRulesTest extends TestCase
{
    public function test_normalize_dart_limit_snaps_and_clamps(): void
    {
        $this->assertNull(DartLimitRules::normalizeDartLimit(null));
        $this->assertNull(DartLimitRules::normalizeDartLimit(0));
        $this->assertSame(15, DartLimitRules::normalizeDartLimit(14));
        $this->assertSame(45, DartLimitRules::normalizeDartLimit(46));
        $this->assertSame(99, DartLimitRules::normalizeDartLimit(100));
    }

    public function test_is_applicable_requires_x01_and_ignores_each_own(): void
    {
        $this->assertTrue(DartLimitRules::isApplicable(45, true));
        $this->assertFalse(DartLimitRules::isApplicable(45, false));
        $this->assertFalse(DartLimitRules::isApplicable(null, true));
        $this->assertFalse(DartLimitRules::isApplicable(45, true, 'each_own'));
        $this->assertTrue(DartLimitRules::isApplicable(45, true, 'one_device'));
    }

    public function test_is_reached_when_every_player_meets_limit(): void
    {
        $this->assertFalse(DartLimitRules::isReached(15, [1 => 12, 2 => 15]));
        $this->assertTrue(DartLimitRules::isReached(15, [1 => 15, 2 => 18]));
        $this->assertFalse(DartLimitRules::isReached(15, []));
    }

    public function test_h2h_outcome_auto_loss_and_bull_off(): void
    {
        $this->assertSame(DartLimitRules::OUTCOME_BULL_OFF, DartLimitRules::resolveH2hOutcome(null, 80, 10));
        $this->assertSame(DartLimitRules::OUTCOME_AUTO_P2, DartLimitRules::resolveH2hOutcome(50, 80, 10));
        $this->assertSame(DartLimitRules::OUTCOME_AUTO_P1, DartLimitRules::resolveH2hOutcome(50, 10, 80));
        $this->assertSame(DartLimitRules::OUTCOME_BULL_OFF, DartLimitRules::resolveH2hOutcome(50, 80, 80));
        $this->assertSame(DartLimitRules::OUTCOME_BULL_OFF, DartLimitRules::resolveH2hOutcome(50, 10, 20));
    }

    public function test_assert_loss_threshold_requires_dart_limit(): void
    {
        $this->expectException(DomainException::class);
        DartLimitRules::assertValidLossThreshold(50, null);
    }
}
