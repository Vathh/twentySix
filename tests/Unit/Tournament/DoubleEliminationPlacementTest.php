<?php

namespace Tests\Unit\Tournament;

use App\Enums\BracketSide;
use App\Support\Tournament\DoubleEliminationPlacement;
use PHPUnit\Framework\TestCase;

class DoubleEliminationPlacementTest extends TestCase
{
    public function test_lb_final_loser_is_third(): void
    {
        // N=8 → LB rounds 0..3; L3 is final
        $place = DoubleEliminationPlacement::placeForLoser(
            BracketSide::Losers,
            'L3',
            'L3-1',
            8,
            hasLoserDestination: false,
        );

        $this->assertSame(3, $place);
    }

    public function test_lb_semi_loser_is_fourth(): void
    {
        $place = DoubleEliminationPlacement::placeForLoser(
            BracketSide::Losers,
            'L2',
            'L2-1',
            8,
            hasLoserDestination: false,
        );

        $this->assertSame(4, $place);
    }

    public function test_wb_loss_with_drop_has_no_place(): void
    {
        $place = DoubleEliminationPlacement::placeForLoser(
            BracketSide::Winners,
            'W0',
            'W0-1',
            8,
            hasLoserDestination: true,
        );

        $this->assertNull($place);
    }
}
