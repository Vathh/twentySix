<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\PlayoffConsolationSeeding;
use App\Domain\Tournament\PlayoffFirstRoundSeeding;
use PHPUnit\Framework\TestCase;

class PlayoffConsolationSeedingTest extends TestCase
{
    public function test_full_bracket_uses_the_same_pairs_as_the_main_bracket(): void
    {
        $players = [
            ['player_id' => 1, 'group_number' => 1, 'place' => 3],
            ['player_id' => 2, 'group_number' => 1, 'place' => 4],
            ['player_id' => 3, 'group_number' => 2, 'place' => 3],
            ['player_id' => 4, 'group_number' => 2, 'place' => 4],
        ];

        $this->assertSame(
            PlayoffFirstRoundSeeding::pair($players),
            PlayoffConsolationSeeding::pair($players, 4, 99),
        );
    }

    public function test_byes_go_to_better_places_and_do_not_meet(): void
    {
        $players = [
            ['player_id' => 11, 'group_number' => 1, 'place' => 3],
            ['player_id' => 12, 'group_number' => 1, 'place' => 4],
            ['player_id' => 21, 'group_number' => 2, 'place' => 3],
            ['player_id' => 22, 'group_number' => 2, 'place' => 4],
            ['player_id' => 31, 'group_number' => 3, 'place' => 3],
            ['player_id' => 32, 'group_number' => 3, 'place' => 4],
        ];
        $byeId = 99;

        $first = PlayoffConsolationSeeding::pair($players, 8, $byeId);
        $second = PlayoffConsolationSeeding::pair($players, 8, $byeId);

        $this->assertSame($first, $second);
        $this->assertCount(4, $first);

        $byePairs = array_values(array_filter(
            $first,
            fn (array $pair) => $pair[0] === $byeId || $pair[1] === $byeId,
        ));
        $this->assertCount(2, $byePairs);
        $this->assertSame([11, $byeId], $byePairs[0]);
        $this->assertSame([21, $byeId], $byePairs[1]);

        $groupByPlayer = [
            11 => 1, 12 => 1, 21 => 2, 22 => 2, 31 => 3, 32 => 3,
        ];
        foreach ($first as [$a, $b]) {
            $this->assertFalse($a === $byeId && $b === $byeId);
            if ($a !== $byeId && $b !== $byeId) {
                $this->assertNotSame($groupByPlayer[$a], $groupByPlayer[$b]);
            }
        }
    }

    public function test_place_slots_pair_the_same_way_as_real_player_ids(): void
    {
        $players = [
            ['player_id' => 11, 'group_number' => 1, 'place' => 3],
            ['player_id' => 12, 'group_number' => 1, 'place' => 4],
            ['player_id' => 21, 'group_number' => 2, 'place' => 3],
            ['player_id' => 22, 'group_number' => 2, 'place' => 4],
            ['player_id' => 31, 'group_number' => 3, 'place' => 3],
            ['player_id' => 32, 'group_number' => 3, 'place' => 4],
        ];
        $byeId = 99;
        $placeByPlayer = [];
        $slots = [];
        foreach ($players as $player) {
            $placeByPlayer[$player['player_id']] = $player['group_number'].':'.$player['place'];
            $slots[] = [
                'player_id' => -($player['group_number'] * 1000 + $player['place']),
                'group_number' => $player['group_number'],
                'place' => $player['place'],
            ];
        }

        $label = function (int $id) use ($byeId, $placeByPlayer): string {
            if ($id === $byeId) {
                return 'bye';
            }

            return $placeByPlayer[$id];
        };
        $slotLabel = function (int $id) use ($byeId): string {
            if ($id === $byeId) {
                return 'bye';
            }

            $value = -$id;

            return intdiv($value, 1000).':'.($value % 1000);
        };

        $real = array_map(
            fn (array $pair) => [$label($pair[0]), $label($pair[1])],
            PlayoffConsolationSeeding::pair($players, 8, $byeId),
        );
        $marked = array_map(
            fn (array $pair) => [$slotLabel($pair[0]), $slotLabel($pair[1])],
            PlayoffConsolationSeeding::pair($slots, 8, $byeId),
        );

        $this->assertSame($real, $marked);
    }
}
