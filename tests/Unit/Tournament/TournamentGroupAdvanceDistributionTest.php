<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\TournamentGroupAdvanceDistribution;
use App\Support\Tournament\TournamentGroupDistribution;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentGroupAdvanceDistributionTest extends TestCase
{
    public function test_thirty_seven_players_seven_groups_sixteen_bracket(): void
    {
        $sizes = TournamentGroupDistribution::groupSizes(37, 7);

        $this->assertSame([6, 6, 5, 5, 5, 5, 5], $sizes);
        $this->assertSame([3, 3, 2, 2, 2, 2, 2], TournamentGroupAdvanceDistribution::distribute($sizes, 16));
    }

    public function test_thirty_eight_players_seven_groups_keeps_same_sixteen_bracket_distribution(): void
    {
        $sizes = TournamentGroupDistribution::groupSizes(38, 7);

        $this->assertSame([6, 6, 6, 5, 5, 5, 5], $sizes);
        $this->assertSame([3, 3, 2, 2, 2, 2, 2], TournamentGroupAdvanceDistribution::distribute($sizes, 16));
    }

    #[DataProvider('uniformLegacyProvider')]
    public function test_uniform_advance_for_equal_group_sizes(int $players, int $groups, int $advancesPerGroup): void
    {
        $bracketSize = $groups * $advancesPerGroup;
        $sizes = TournamentGroupDistribution::groupSizes($players, $groups);
        $advances = TournamentGroupAdvanceDistribution::distribute($sizes, $bracketSize);

        $this->assertSame(array_fill(0, $groups, $advancesPerGroup), $advances);
    }

    public static function uniformLegacyProvider(): array
    {
        return [
            '8 players 2x2' => [8, 2, 2],
            '16 players 4x2' => [16, 4, 2],
            '32 players 8x4' => [32, 8, 4],
        ];
    }
}
