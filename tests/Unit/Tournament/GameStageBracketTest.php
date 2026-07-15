<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use PHPUnit\Framework\TestCase;

class GameStageBracketTest extends TestCase
{
    public function test_stages_for_bracket_four_includes_semi_third_and_final(): void
    {
        $stages = GameStage::forPlayoffBracketSize(4);

        $this->assertSame(
            [GameStage::GROUP, GameStage::SEMI, GameStage::THIRD, GameStage::FINAL],
            $stages,
        );
    }

    public function test_stages_for_bracket_sixteen_includes_eight_through_final(): void
    {
        $stages = GameStage::forPlayoffBracketSize(16);

        $this->assertSame(
            [
                GameStage::GROUP,
                GameStage::EIGHT,
                GameStage::QUARTER,
                GameStage::SEMI,
                GameStage::THIRD,
                GameStage::FINAL,
            ],
            $stages,
        );
    }

    public function test_stages_for_bracket_two_only_group_and_final(): void
    {
        $stages = GameStage::forPlayoffBracketSize(2);

        $this->assertSame([GameStage::GROUP, GameStage::FINAL], $stages);
    }
}
