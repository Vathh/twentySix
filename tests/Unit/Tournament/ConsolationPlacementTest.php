<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\ConsolationPlacement;
use App\Enums\GameStage;
use Tests\TestCase;

class ConsolationPlacementTest extends TestCase
{
    public function test_podium_places_are_offset_by_main_bracket_size(): void
    {
        $this->assertSame(5, ConsolationPlacement::podiumWinnerPlace(GameStage::FINAL, 4));
        $this->assertSame(7, ConsolationPlacement::podiumWinnerPlace(GameStage::THIRD, 4));
        $this->assertNull(ConsolationPlacement::podiumWinnerPlace(GameStage::QUARTER, 8));
    }

    public function test_shared_quarter_place_uses_se_buckets_plus_offset(): void
    {
        $this->assertSame(13, ConsolationPlacement::sharedPlace(GameStage::QUARTER, 8, 8));
        $this->assertSame(17, ConsolationPlacement::sharedPlace(GameStage::EIGHT, 8, 16));
    }
}
