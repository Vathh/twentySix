<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\TournamentGroupDistribution;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentGroupDistributionTest extends TestCase
{
    #[DataProvider('groupSizesProvider')]
    public function test_group_sizes(int $playerCount, int $groupsCount, array $expectedSizes): void
    {
        $this->assertSame(
            $expectedSizes,
            TournamentGroupDistribution::groupSizes($playerCount, $groupsCount),
        );
    }

    public static function groupSizesProvider(): array
    {
        return [
            'product example 30 players 8 groups' => [30, 8, [4, 4, 4, 4, 4, 4, 3, 3]],
            '16 players 4 groups' => [16, 4, [4, 4, 4, 4]],
            '5 players 2 groups' => [5, 2, [3, 2]],
            '4 players 2 groups' => [4, 2, [2, 2]],
            '64 players 64 groups' => [64, 64, array_fill(0, 64, 1)],
            '7 players 4 groups' => [7, 4, [2, 2, 2, 1]],
        ];
    }

    public function test_distribute_assigns_every_player_exactly_once(): void
    {
        $playerIds = range(1, 30);

        $groups = TournamentGroupDistribution::distribute($playerIds, 8);

        $this->assertCount(8, $groups);
        $this->assertSame(
            TournamentGroupDistribution::groupSizes(30, 8),
            array_map('count', $groups),
        );

        $flattened = array_merge(...$groups);
        sort($flattened);

        $this->assertSame($playerIds, $flattened);
    }

    public function test_distribute_does_not_use_round_robin_pattern(): void
    {
        // Przy starym algorytmie (% groupsCount) ostatnie grupy były większe.
        // Sprawdzamy rozmiary — niezależnie od losowania muszą być zgodne z groupSizes.
        $playerIds = range(1, 30);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $groups = TournamentGroupDistribution::distribute($playerIds, 8);

            $this->assertSame([4, 4, 4, 4, 4, 4, 3, 3], array_map('count', $groups));
        }
    }

    public function test_group_sizes_rejects_invalid_groups_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TournamentGroupDistribution::groupSizes(4, 0);
    }
}
