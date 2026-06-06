<?php

namespace Tests\Unit\Tournament;

use App\Support\Tournament\PlayoffFirstRoundPairing;
use RuntimeException;
use Tests\TestCase;

class PlayoffFirstRoundPairingTest extends TestCase
{
    public function test_pairs_four_players_from_two_groups_without_same_group_matchups(): void
    {
        $advancing = [
            ['player_id' => 1, 'group_number' => 1],
            ['player_id' => 2, 'group_number' => 1],
            ['player_id' => 3, 'group_number' => 2],
            ['player_id' => 4, 'group_number' => 2],
        ];

        $groupByPlayer = [1 => 1, 2 => 1, 3 => 2, 4 => 2];

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $pairs = PlayoffFirstRoundPairing::pair($advancing);

            $this->assertCount(2, $pairs);
            $this->assertTrue(PlayoffFirstRoundPairing::pairsSatisfyGroupConstraint($pairs, $groupByPlayer));
        }
    }

    public function test_pairs_eight_players_from_four_groups(): void
    {
        $advancing = [
            ['player_id' => 1, 'group_number' => 1],
            ['player_id' => 2, 'group_number' => 1],
            ['player_id' => 3, 'group_number' => 2],
            ['player_id' => 4, 'group_number' => 2],
            ['player_id' => 5, 'group_number' => 3],
            ['player_id' => 6, 'group_number' => 3],
            ['player_id' => 7, 'group_number' => 4],
            ['player_id' => 8, 'group_number' => 4],
        ];

        $groupByPlayer = collect($advancing)->mapWithKeys(
            fn (array $row) => [$row['player_id'] => $row['group_number']],
        )->all();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $pairs = PlayoffFirstRoundPairing::pair($advancing);

            $this->assertCount(4, $pairs);
            $this->assertTrue(PlayoffFirstRoundPairing::pairsSatisfyGroupConstraint($pairs, $groupByPlayer));
        }
    }

    public function test_rejects_two_players_from_same_group(): void
    {
        $this->expectException(RuntimeException::class);

        PlayoffFirstRoundPairing::pair([
            ['player_id' => 1, 'group_number' => 1],
            ['player_id' => 2, 'group_number' => 1],
        ]);
    }

    public function test_impossible_four_player_single_group_configuration_fails(): void
    {
        $this->expectException(RuntimeException::class);

        PlayoffFirstRoundPairing::pair([
            ['player_id' => 1, 'group_number' => 1],
            ['player_id' => 2, 'group_number' => 1],
            ['player_id' => 3, 'group_number' => 1],
            ['player_id' => 4, 'group_number' => 1],
        ]);
    }
}
