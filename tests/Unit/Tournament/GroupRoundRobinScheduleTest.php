<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\GroupRoundRobinSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupRoundRobinScheduleTest extends TestCase
{
    #[DataProvider('groupSizes')]
    public function test_schedule_is_a_full_round_robin_with_even_referees(int $playerCount): void
    {
        $playerIds = range(101, 100 + $playerCount);
        $schedule = GroupRoundRobinSchedule::build($playerIds);

        $expectedMatches = $playerCount * ($playerCount - 1) / 2;
        $this->assertCount($expectedMatches, $schedule);
        $this->assertSame(
            range(1, $expectedMatches),
            array_column($schedule, 'sequence'),
        );
        $this->assertSame($schedule, GroupRoundRobinSchedule::build($playerIds));

        $pairs = [];
        $indexOf = array_flip($playerIds);
        $refereeCounts = array_fill_keys($playerIds, 0);

        foreach ($schedule as $match) {
            $this->assertLessThan(
                $indexOf[$match['player2_id']],
                $indexOf[$match['player1_id']],
            );
            $pair = [$match['player1_id'], $match['player2_id']];
            sort($pair);
            $pairs[] = $pair[0].'-'.$pair[1];

            $this->assertNotContains($match['referee_player_id'], [$match['player1_id'], $match['player2_id']]);
            $this->assertArrayHasKey($match['referee_player_id'], $refereeCounts);
            $refereeCounts[$match['referee_player_id']]++;
        }

        $this->assertCount($expectedMatches, array_unique($pairs));
        $this->assertLessThanOrEqual(1, max($refereeCounts) - min($refereeCounts));

        $roundSize = intdiv($playerCount, 2);
        for ($index = 0; $index < count($schedule) - 1; $index++) {
            $sameRound = intdiv($index, $roundSize) === intdiv($index + 1, $roundSize);
            $overlap = count(array_intersect(
                [$schedule[$index]['player1_id'], $schedule[$index]['player2_id']],
                [$schedule[$index + 1]['player1_id'], $schedule[$index + 1]['player2_id']],
            ));

            if ($sameRound) {
                $this->assertSame(0, $overlap, "Mecz {$index} i następny są w jednej rundzie.");
            }

            if ($playerCount % 2 === 0 && $playerCount >= 6) {
                $this->assertSame(0, $overlap, "Przy {$playerCount} osobach nikt nie gra dwa razy z rzędu.");
            }

            if ($playerCount === 4 && ! $sameRound) {
                $this->assertSame(1, $overlap, 'Przy czwórce powtórka jest tylko na granicy rund.');
            }
        }
    }

    public static function groupSizes(): array
    {
        $sizes = [];
        foreach (range(3, 12) as $size) {
            $sizes["{$size} players"] = [$size];
        }

        return $sizes;
    }

    public function test_three_players_each_referee_once(): void
    {
        $schedule = GroupRoundRobinSchedule::build([7, 8, 9]);
        $counts = array_count_values(array_column($schedule, 'referee_player_id'));

        $this->assertSame([7 => 1, 8 => 1, 9 => 1], $counts);
    }
}
