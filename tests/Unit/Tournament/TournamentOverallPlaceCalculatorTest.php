<?php

namespace Tests\Unit\Tournament;

use App\Enums\GameStage;
use App\Support\Tournament\TournamentOverallPlaceCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TournamentOverallPlaceCalculatorTest extends TestCase
{
    public function test_thirty_seven_player_tournament_assigns_ex_aequo_group_places(): void
    {
        $calculator = new TournamentOverallPlaceCalculator();

        $rows = collect([
            ['player_id' => 1, 'elimination_stage' => GameStage::FINAL, 'group_place' => null, 'current_place' => 1],
            ['player_id' => 2, 'elimination_stage' => GameStage::FINAL, 'group_place' => null, 'current_place' => 2],
            ['player_id' => 3, 'elimination_stage' => GameStage::THIRD, 'group_place' => null, 'current_place' => 3],
            ['player_id' => 4, 'elimination_stage' => GameStage::THIRD, 'group_place' => null, 'current_place' => 4],
        ]);

        foreach ([501, 502, 503, 504] as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::QUARTER,
                'group_place' => null,
                'current_place' => null,
            ]);
        }

        foreach (range(601, 606) as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::GROUP,
                'group_place' => 2,
                'current_place' => null,
            ]);
        }

        foreach (range(701, 707) as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::GROUP,
                'group_place' => 3,
                'current_place' => null,
            ]);
        }

        foreach (range(801, 807) as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::GROUP,
                'group_place' => 4,
                'current_place' => null,
            ]);
        }

        foreach (range(901, 907) as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::GROUP,
                'group_place' => 5,
                'current_place' => null,
            ]);
        }

        foreach ([1001, 1002] as $playerId) {
            $rows->push([
                'player_id' => $playerId,
                'elimination_stage' => GameStage::GROUP,
                'group_place' => 6,
                'current_place' => null,
            ]);
        }

        $places = $calculator->calculate(8, $rows);

        $this->assertSame(1, $places[1]);
        $this->assertSame(2, $places[2]);
        $this->assertSame(3, $places[3]);
        $this->assertSame(4, $places[4]);
        $this->assertSame(5, $places[501]);
        $this->assertSame(5, $places[504]);
        $this->assertSame(9, $places[601]);
        $this->assertSame(9, $places[606]);
        $this->assertSame(15, $places[701]);
        $this->assertSame(22, $places[801]);
        $this->assertSame(29, $places[901]);
        $this->assertSame(36, $places[1001]);
        $this->assertSame(36, $places[1002]);
        $this->assertCount(37, $places);
    }

    public function test_bracket_sixteen_assigns_eight_place_to_first_round_losers(): void
    {
        $calculator = new TournamentOverallPlaceCalculator();

        $rows = Collection::make(range(1, 8))->map(fn (int $playerId) => [
            'player_id' => 100 + $playerId,
            'elimination_stage' => GameStage::EIGHT,
            'group_place' => null,
            'current_place' => null,
        ]);

        $places = $calculator->calculate(16, $rows);

        foreach (range(101, 108) as $playerId) {
            $this->assertSame(9, $places[$playerId]);
        }
    }
}
