<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\PlayoffFirstRoundSeeding;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PlayoffFirstRoundSeedingTest extends TestCase
{
    public function test_two_groups_of_two_cross_first_with_second(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(2, 2));

        $this->assertSame([
            [11, 22],
            [21, 12],
        ], $pairs);
    }

    public function test_four_groups_of_two_follow_adjacent_cross_and_opposite_halves(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(4, 2));

        $this->assertSame([
            [11, 22],
            [31, 42],
            [21, 12],
            [41, 32],
        ], $pairs);
    }

    public function test_eight_groups_of_two_follow_world_cup_round_of_16(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(8, 2));

        $this->assertSame([
            [11, 22],
            [31, 42],
            [51, 62],
            [71, 82],
            [21, 12],
            [41, 32],
            [61, 52],
            [81, 72],
        ], $pairs);
    }

    public function test_two_groups_of_four_separate_first_and_second_into_opposite_halves(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(2, 4));

        $this->assertSame([
            [11, 24],
            [22, 13],
            [21, 14],
            [12, 23],
        ], $pairs);
    }

    public function test_four_groups_of_four_keep_same_bundle_out_of_the_next_round_pair(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(4, 4));

        $this->assertSame([
            [11, 24],
            [31, 44],
            [12, 23],
            [32, 43],
            [21, 14],
            [41, 34],
            [22, 13],
            [42, 33],
        ], $pairs);
    }

    public function test_group_winners_only_are_paired_with_the_next_group(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(4, 1));

        $this->assertSame([
            [11, 21],
            [31, 41],
        ], $pairs);
    }

    public function test_eight_group_winners_stay_in_adjacent_pairs(): void
    {
        $pairs = PlayoffFirstRoundSeeding::pair($this->uniform(8, 1));

        $this->assertSame([
            [11, 21],
            [31, 41],
            [51, 61],
            [71, 81],
        ], $pairs);
    }

    public function test_uneven_seven_groups_give_winners_the_weakest_available_opponents(): void
    {
        $players = [];

        foreach ([1 => 3, 2 => 3, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 7 => 2] as $group => $advances) {
            for ($place = 1; $place <= $advances; $place++) {
                $players[] = [
                    'player_id' => $group * 10 + $place,
                    'group_number' => $group,
                    'place' => $place,
                ];
            }
        }

        $pairs = PlayoffFirstRoundSeeding::pair($players);

        $this->assertSame([
            [11, 23],
            [31, 22],
            [51, 42],
            [71, 62],
            [21, 13],
            [41, 32],
            [61, 52],
            [12, 72],
        ], $pairs);
        $this->assertSame($pairs, PlayoffFirstRoundSeeding::pair($players));
        $this->assertTrue(PlayoffFirstRoundSeeding::pairsSatisfyGroupConstraint(
            $pairs,
            $this->groupByPlayer($players),
        ));
    }

    public function test_more_group_winners_than_matches_still_minimizes_winner_vs_winner(): void
    {
        $players = [];

        foreach ([1 => 2, 2 => 2, 3 => 1, 4 => 1, 5 => 1, 6 => 1] as $group => $advances) {
            for ($place = 1; $place <= $advances; $place++) {
                $players[] = [
                    'player_id' => $group * 10 + $place,
                    'group_number' => $group,
                    'place' => $place,
                ];
            }
        }

        $pairs = PlayoffFirstRoundSeeding::pair($players);

        $this->assertSame([
            [11, 22],
            [31, 41],
            [21, 12],
            [51, 61],
        ], $pairs);

        $winnerVsWinner = 0;

        foreach ($pairs as [$left, $right]) {
            if ($left % 10 === 1 && $right % 10 === 1) {
                $winnerVsWinner++;
            }
        }

        $this->assertSame(2, $winnerVsWinner);
    }

    public function test_rejects_odd_advancer_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PlayoffFirstRoundSeeding::pair([
            ['player_id' => 11, 'group_number' => 1, 'place' => 1],
            ['player_id' => 21, 'group_number' => 2, 'place' => 1],
            ['player_id' => 31, 'group_number' => 3, 'place' => 1],
        ]);
    }

    public function test_rejects_two_players_from_same_group(): void
    {
        $this->expectException(RuntimeException::class);

        PlayoffFirstRoundSeeding::pair([
            ['player_id' => 1, 'group_number' => 1, 'place' => 1],
            ['player_id' => 2, 'group_number' => 1, 'place' => 2],
        ]);
    }

    public function test_impossible_four_player_single_group_configuration_fails(): void
    {
        $this->expectException(RuntimeException::class);

        PlayoffFirstRoundSeeding::pair([
            ['player_id' => 1, 'group_number' => 1, 'place' => 1],
            ['player_id' => 2, 'group_number' => 1, 'place' => 2],
            ['player_id' => 3, 'group_number' => 1, 'place' => 3],
            ['player_id' => 4, 'group_number' => 1, 'place' => 4],
        ]);
    }

    /**
     * @return list<array{player_id: int, group_number: int, place: int}>
     */
    private function uniform(int $groups, int $perGroup): array
    {
        $players = [];

        for ($group = 1; $group <= $groups; $group++) {
            for ($place = 1; $place <= $perGroup; $place++) {
                $players[] = [
                    'player_id' => $group * 10 + $place,
                    'group_number' => $group,
                    'place' => $place,
                ];
            }
        }

        return $players;
    }

    /**
     * @param  list<array{player_id: int, group_number: int, place: int}>  $players
     * @return array<int, int>
     */
    private function groupByPlayer(array $players): array
    {
        $map = [];

        foreach ($players as $player) {
            $map[$player['player_id']] = $player['group_number'];
        }

        return $map;
    }
}
