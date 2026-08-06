<?php

namespace Tests\Unit\Tournament;

use App\Enums\BracketSide;
use App\Factories\DoubleEliminationBracketFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DoubleEliminationBracketFactoryTest extends TestCase
{
    private DoubleEliminationBracketFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new DoubleEliminationBracketFactory();
    }

    #[DataProvider('sizeProvider')]
    public function test_creates_expected_structure(int $size, int $wbFirstRound, int $lbRounds, int $expectedGames): void
    {
        $pairs = [];
        for ($i = 1; $i <= $size; $i += 2) {
            $pairs[] = [$i, $i + 1];
        }

        $games = $this->factory->create(1, $size, $pairs, resetGrandFinal: true);

        $this->assertCount($expectedGames, $games);
        $this->assertSame(
            $wbFirstRound,
            $games->where('round', 'W0')->whereNotNull('player1Id')->count(),
        );
        $this->assertSame(1, $games->where('slot', 'GF1')->count());
        $this->assertSame(1, $games->where('slot', 'GF2')->count());
        $this->assertTrue($games->where('bracketSide', BracketSide::Losers)->isNotEmpty());

        $maxL = $games->filter(fn ($g) => str_starts_with($g->round, 'L'))
            ->map(fn ($g) => (int) substr($g->round, 1))
            ->max();
        $this->assertSame($lbRounds - 1, $maxL);
    }

    public static function sizeProvider(): array
    {
        // games = WB (N-1) + LB (N-2) + GF1 + GF2
        // N=4: WB3 + LB2 + 2 = 7; N=8: WB7 + LB6 + 2 = 15; N=16: WB15 + LB14 + 2 = 31
        return [
            '4' => [4, 2, 2, 7],
            '8' => [8, 4, 4, 15],
            '16' => [16, 8, 6, 31],
        ];
    }

    public function test_single_gf_without_reset(): void
    {
        $pairs = [[1, 2], [3, 4]];
        $games = $this->factory->create(1, 4, $pairs, resetGrandFinal: false);

        $this->assertSame(0, $games->where('slot', 'GF2')->count());
        $this->assertSame(1, $games->where('slot', 'GF1')->count());
    }
}
