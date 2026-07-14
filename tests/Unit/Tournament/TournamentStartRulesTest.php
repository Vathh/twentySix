<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\TournamentGroupAdvanceDistribution;
use App\Support\Tournament\TournamentStartRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentStartRulesTest extends TestCase
{
    #[DataProvider('powerOfTwoProvider')]
    public function test_is_power_of_two(int $value, bool $expected): void
    {
        $this->assertSame($expected, TournamentStartRules::isPowerOfTwo($value));
    }

    public static function powerOfTwoProvider(): array
    {
        return [
            'zero' => [0, false],
            'one' => [1, true],
            'two' => [2, true],
            'three' => [3, false],
            'four' => [4, true],
            'thirty two' => [32, true],
            'sixty four' => [64, true],
            'sixty five' => [65, false],
        ];
    }

    public function test_allowed_group_counts_for_eight_players(): void
    {
        $this->assertSame([2], TournamentStartRules::allowedGroupCountsForPlayers(8));
    }

    public function test_allowed_group_counts_for_twelve_players(): void
    {
        $this->assertSame([2, 3, 4], TournamentStartRules::allowedGroupCountsForPlayers(12));
    }

    public function test_allowed_group_counts_for_thirty_seven_players_includes_seven_groups(): void
    {
        $this->assertContains(7, TournamentStartRules::allowedGroupCountsForPlayers(37));
    }

    public function test_allowed_group_counts_for_four_players_is_empty(): void
    {
        $this->assertSame([], TournamentStartRules::allowedGroupCountsForPlayers(4));
    }

    public function test_max_players_in_largest_group(): void
    {
        $this->assertSame(4, TournamentStartRules::maxPlayersInLargestGroup(8, 2));
        $this->assertSame(4, TournamentStartRules::maxPlayersInLargestGroup(13, 4));
        $this->assertSame(6, TournamentStartRules::maxPlayersInLargestGroup(37, 7));
    }

    public function test_allowed_bracket_sizes_for_thirty_seven_players_and_seven_groups(): void
    {
        $this->assertSame([8, 16, 32], TournamentStartRules::allowedPlayoffBracketSizes(37, 7));
    }

    public function test_bracket_option_label(): void
    {
        $this->assertSame('1/8 finału — 16 graczy awansujących', TournamentStartRules::bracketOptionLabel(16));
    }

    public function test_start_config_preview_for_thirty_seven_players_seven_groups_sixteen_bracket(): void
    {
        $preview = TournamentStartRules::startConfigPreview(37);

        $this->assertSame([6, 6, 5, 5, 5, 5, 5], $preview[7][16]['groupSizes']);
        $this->assertSame([3, 3, 2, 2, 2, 2, 2], $preview[7][16]['advances']);
    }
}
