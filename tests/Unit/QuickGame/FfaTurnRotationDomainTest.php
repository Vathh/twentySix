<?php

namespace Tests\Unit\QuickGame;

use App\Domain\QuickGame\FfaTurnRotationDomain;
use PHPUnit\Framework\TestCase;

class FfaTurnRotationDomainTest extends TestCase
{
    public function test_active_player_ids_filters_out_left_players(): void
    {
        $active = FfaTurnRotationDomain::activePlayerIds([10, 20, 30, 40], [20, 40]);

        $this->assertSame([10, 30], $active);
    }

    public function test_next_index_after_wraps_and_skips_left_players(): void
    {
        $playerIds = [10, 20, 30, 40];

        $this->assertSame(1, FfaTurnRotationDomain::nextIndexAfter(0, $playerIds, []));
        $this->assertSame(0, FfaTurnRotationDomain::nextIndexAfter(3, $playerIds, []));
        $this->assertSame(2, FfaTurnRotationDomain::nextIndexAfter(0, $playerIds, [20]));
    }

    public function test_next_index_after_returns_from_index_when_nobody_active(): void
    {
        $playerIds = [10, 20];

        $this->assertSame(0, FfaTurnRotationDomain::nextIndexAfter(0, $playerIds, [10, 20]));
    }

    public function test_normalize_index_at_keeps_index_when_player_active(): void
    {
        $playerIds = [10, 20, 30];

        $this->assertSame(1, FfaTurnRotationDomain::normalizeIndexAt(1, $playerIds, [30]));
    }

    public function test_normalize_index_at_skips_to_next_active_when_left(): void
    {
        $playerIds = [10, 20, 30];

        $this->assertSame(2, FfaTurnRotationDomain::normalizeIndexAt(1, $playerIds, [20]));
    }
}
