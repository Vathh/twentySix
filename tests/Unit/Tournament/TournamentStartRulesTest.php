<?php

namespace Tests\Unit\Tournament;

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

    public function test_allowed_advance_for_eight_groups(): void
    {
        $this->assertSame([1, 2, 4], TournamentStartRules::allowedAdvancePerGroup(8));
    }

    public function test_allowed_advance_for_sixty_four_groups_mvp_cap(): void
    {
        $this->assertSame([], TournamentStartRules::allowedAdvancePerGroup(64));
    }

    public function test_allowed_advance_for_thirty_two_groups(): void
    {
        $this->assertSame([1], TournamentStartRules::allowedAdvancePerGroup(32));
    }

    public function test_allowed_group_counts(): void
    {
        $this->assertSame([2, 4, 8, 16, 32, 64], TournamentStartRules::allowedGroupCounts());
    }

    public function test_advances_by_group_count_includes_eight_groups(): void
    {
        $map = TournamentStartRules::advancesByGroupCount();

        $this->assertSame([1, 2, 4], $map[8]);
    }
}
