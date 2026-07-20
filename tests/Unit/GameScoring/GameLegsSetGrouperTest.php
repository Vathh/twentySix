<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\GameLegsSetGrouper;
use App\Support\GameScoring\MatchFormat;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class GameLegsSetGrouperTest extends TestCase
{
    public function test_single_set_keeps_one_group(): void
    {
        $legs = $this->legs([
            [1, 1, true],
            [2, 2, true],
            [3, 1, true],
        ]);

        $sets = GameLegsSetGrouper::group($legs, MatchFormat::default(), 1, 2);

        $this->assertCount(1, $sets);
        $this->assertSame(1, $sets[0]['setNumber']);
        $this->assertSame([1, 2, 3], array_column($sets[0]['legs'], 'legInSetNumber'));
    }

    public function test_multi_set_first_to_six_legs_groups_into_two_sets(): void
    {
        // Set 1: P1 wins 6–4 (10 legs). Set 2: P2 wins 6–2 (8 legs). Total 18 global legs.
        $winners = array_merge(
            [1, 2, 1, 2, 1, 2, 1, 2, 1, 1], // 6–4 for P1
            [2, 1, 2, 1, 2, 2, 2, 2],       // 6–2 for P2
        );

        $rows = [];
        foreach ($winners as $i => $winner) {
            $rows[] = [$i + 1, $winner, true];
        }

        $format = new MatchFormat(
            startingScore: 501,
            legsToWinSet: 6,
            setsToWinMatch: 2,
        );

        $sets = GameLegsSetGrouper::group($this->legs($rows), $format, 1, 2);

        $this->assertCount(2, $sets);
        $this->assertCount(10, $sets[0]['legs']);
        $this->assertCount(8, $sets[1]['legs']);
        $this->assertSame(1, $sets[0]['legs'][0]['legInSetNumber']);
        $this->assertSame(10, $sets[0]['legs'][9]['legInSetNumber']);
        $this->assertSame(1, $sets[1]['legs'][0]['legInSetNumber']);
        $this->assertSame(8, $sets[1]['legs'][7]['legInSetNumber']);
    }

    public function test_open_leg_stays_in_current_set(): void
    {
        $format = new MatchFormat(
            startingScore: 501,
            legsToWinSet: 3,
            setsToWinMatch: 2,
        );

        $legs = $this->legs([
            [1, 1, true],
            [2, 1, true],
            [3, 1, true], // set 1 over 3–0
            [4, 2, true],
            [5, null, false], // open
        ]);

        $sets = GameLegsSetGrouper::group($legs, $format, 1, 2);

        $this->assertCount(2, $sets);
        $this->assertCount(3, $sets[0]['legs']);
        $this->assertCount(2, $sets[1]['legs']);
        $this->assertNull($sets[1]['legs'][1]['leg']->winner_id);
    }

    /**
     * @param  list<array{0: int, 1: ?int, 2: bool}>  $rows  [legNumber, winnerId, finished]
     */
    private function legs(array $rows): Collection
    {
        return collect($rows)->map(function (array $row) {
            [$legNumber, $winnerId, $finished] = $row;

            return [
                'leg' => (object) [
                    'leg_number' => $legNumber,
                    'winner_id' => $winnerId,
                    'finished_at' => $finished ? '2026-01-01 12:00:00' : null,
                ],
                'visits' => collect(),
                'playerStats' => collect(),
            ];
        });
    }
}
