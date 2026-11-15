<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\PlayoffConsolationSeeding;
use App\Domain\Tournament\PlayoffFirstRoundSeeding;
use App\Domain\Tournament\TournamentGroupAdvanceDistribution;
use App\Domain\Tournament\TournamentGroupDistribution;
use App\Domain\Tournament\TournamentStartRules;
use App\Support\Tournament\PlayoffByePairing;
use PHPUnit\Framework\TestCase;
use Throwable;

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

    public function test_thirteen_players_in_a_bracket_of_sixteen_keep_every_player(): void
    {
        $players = [
            ['player_id' => 13, 'group_number' => 1, 'place' => 3],
            ['player_id' => 14, 'group_number' => 1, 'place' => 4],
            ['player_id' => 15, 'group_number' => 1, 'place' => 5],
            ['player_id' => 16, 'group_number' => 1, 'place' => 6],
            ['player_id' => 23, 'group_number' => 2, 'place' => 3],
            ['player_id' => 24, 'group_number' => 2, 'place' => 4],
            ['player_id' => 25, 'group_number' => 2, 'place' => 5],
            ['player_id' => 33, 'group_number' => 3, 'place' => 3],
            ['player_id' => 34, 'group_number' => 3, 'place' => 4],
            ['player_id' => 35, 'group_number' => 3, 'place' => 5],
            ['player_id' => 43, 'group_number' => 4, 'place' => 3],
            ['player_id' => 44, 'group_number' => 4, 'place' => 4],
            ['player_id' => 45, 'group_number' => 4, 'place' => 5],
        ];
        $byeId = 99;

        $pairs = PlayoffConsolationSeeding::pair($players, 16, $byeId);

        $this->assertSame([
            [13, $byeId],
            [23, $byeId],
            [33, $byeId],
            [43, 16],
            [24, 15],
            [44, 35],
            [14, 25],
            [34, 45],
        ], $pairs);
        $this->assertSame($pairs, PlayoffConsolationSeeding::pair($players, 16, $byeId));

        $seen = [];
        foreach ($pairs as [$left, $right]) {
            foreach ([$left, $right] as $id) {
                if ($id !== $byeId) {
                    $seen[] = $id;
                }
            }
        }
        sort($seen);
        $this->assertSame(array_column($players, 'player_id'), $seen);
    }

    public function test_legal_start_configs_pair_main_and_consolation_without_dropping_players(): void
    {
        $byeId = 999;
        $failures = [];
        $checked = 0;

        for ($playerCount = 6; $playerCount <= 32; $playerCount++) {
            foreach (TournamentStartRules::allowedGroupCountsForPlayers($playerCount) as $groups) {
                foreach (TournamentStartRules::allowedPlayoffBracketSizes($playerCount, $groups) as $mainSize) {
                    $sizes = TournamentGroupDistribution::groupSizes($playerCount, $groups);
                    $advances = TournamentGroupAdvanceDistribution::distribute($sizes, $mainSize);
                    [$main, $consolation] = $this->splitRoster($sizes, $advances);

                    if (count($consolation) < 2) {
                        continue;
                    }

                    $consolationSize = PlayoffByePairing::nextPowerOfTwo(count($consolation));
                    if ($consolationSize > TournamentStartRules::MAX_BRACKET_SIZE) {
                        continue;
                    }

                    $label = sprintf(
                        '%d graczy, %d grup, główna %d (awans %s), pocieszenie %d w drabince %d',
                        $playerCount,
                        $groups,
                        $mainSize,
                        implode(',', $advances),
                        count($consolation),
                        $consolationSize,
                    );

                    try {
                        $mainPairs = PlayoffFirstRoundSeeding::pair($main);
                        $consolationPairs = PlayoffConsolationSeeding::pair($consolation, $consolationSize, $byeId);
                        $this->assertPairsCover(
                            $mainPairs,
                            $main,
                            intdiv($mainSize, 2),
                            0,
                            $label.' / główna',
                        );
                        $this->assertPairsCover(
                            $consolationPairs,
                            $consolation,
                            intdiv($consolationSize, 2),
                            $consolationSize - count($consolation),
                            $label.' / pocieszenie',
                            $byeId,
                        );
                        $this->assertSame(
                            $consolationPairs,
                            PlayoffConsolationSeeding::pair($consolation, $consolationSize, $byeId),
                            $label.' nie jest stabilne',
                        );
                    } catch (Throwable $e) {
                        $failures[] = $label.': '.$e->getMessage();
                    }

                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(40, $checked);
        $this->assertSame([], $failures, implode("\n", $failures));
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

    /**
     * @param  list<int>  $groupSizes
     * @param  list<int>  $advances
     * @return array{0: list<array{player_id: int, group_number: int, place: int}>, 1: list<array{player_id: int, group_number: int, place: int}>}
     */
    private function splitRoster(array $groupSizes, array $advances): array
    {
        $main = [];
        $consolation = [];
        $id = 1;

        foreach ($groupSizes as $index => $size) {
            for ($place = 1; $place <= $size; $place++) {
                $player = [
                    'player_id' => $id++,
                    'group_number' => $index + 1,
                    'place' => $place,
                ];
                if ($place <= $advances[$index]) {
                    $main[] = $player;
                } else {
                    $consolation[] = $player;
                }
            }
        }

        return [$main, $consolation];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs
     * @param  list<array{player_id: int, group_number: int, place: int}>  $players
     */
    private function assertPairsCover(
        array $pairs,
        array $players,
        int $expectedPairCount,
        int $expectedByes,
        string $label,
        int $byeId = 0,
    ): void {
        $this->assertCount($expectedPairCount, $pairs, $label);

        $groupOf = [];
        foreach ($players as $player) {
            $groupOf[$player['player_id']] = $player['group_number'];
        }

        $seen = [];
        $byes = 0;
        foreach ($pairs as [$left, $right]) {
            foreach ([$left, $right] as $id) {
                if ($byeId !== 0 && $id === $byeId) {
                    $byes++;
                    continue;
                }
                $seen[] = $id;
            }
            if (($byeId === 0 || ($left !== $byeId && $right !== $byeId)) && ($groupOf[$left] ?? null) === ($groupOf[$right] ?? null)) {
                $this->fail($label.": ta sama grupa {$left} vs {$right}");
            }
        }

        sort($seen);
        $ids = array_column($players, 'player_id');
        sort($ids);
        $this->assertSame($ids, $seen, $label);
        $this->assertSame($expectedByes, $byes, $label);
    }
}
