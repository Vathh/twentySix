<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\DoubleEliminationMatchFormatMap;
use App\Enums\GameStage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DoubleEliminationMatchFormatMapTest extends TestCase
{
    #[DataProvider('roundProvider')]
    public function test_maps_de_rounds_to_form_stages(int $size, string $round, GameStage $expected): void
    {
        $this->assertSame($expected, DoubleEliminationMatchFormatMap::stageForRound($round, $size));
    }

    public static function roundProvider(): array
    {
        return [
            '4 W0' => [4, 'W0', GameStage::SEMI],
            '4 W1' => [4, 'W1', GameStage::FINAL],
            '4 L0' => [4, 'L0', GameStage::SEMI],
            '4 L1' => [4, 'L1', GameStage::FINAL],
            '4 GF' => [4, 'GF', GameStage::FINAL],
            '4 GF2' => [4, 'GF2', GameStage::FINAL],
            '8 W0' => [8, 'W0', GameStage::QUARTER],
            '8 W1' => [8, 'W1', GameStage::SEMI],
            '8 W2' => [8, 'W2', GameStage::FINAL],
            '8 L0' => [8, 'L0', GameStage::SEMI],
            '8 L3' => [8, 'L3', GameStage::FINAL],
        ];
    }
}
