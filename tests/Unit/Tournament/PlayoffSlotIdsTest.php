<?php

namespace Tests\Unit\Tournament;

use App\Domain\Game\WinnerDestination;
use App\Enums\PlayerSlot;
use App\Support\Tournament\PlayoffSlotIds;
use PHPUnit\Framework\TestCase;

class PlayoffSlotIdsTest extends TestCase
{
    public function test_destination_parse_roundtrip(): void
    {
        $dest = WinnerDestination::parse(PlayoffSlotIds::destination('SEMI_1', 'A'));

        $this->assertSame('SEMI_1', $dest->playoffSlot);
        $this->assertSame(PlayerSlot::A, $dest->playerSlot);
    }

    public function test_stage_for_match_counts(): void
    {
        $this->assertSame('SIXTYFOUR', PlayoffSlotIds::stageForFirstRoundMatchCount(64)->value);
        $this->assertSame('THIRTYTWO', PlayoffSlotIds::stageForFirstRoundMatchCount(32)->value);
        $this->assertSame('FINAL', PlayoffSlotIds::stageForFirstRoundMatchCount(1)->value);
    }
}
