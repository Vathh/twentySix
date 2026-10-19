<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\H2hLegVisitRows;
use PHPUnit\Framework\TestCase;

class H2hLegVisitRowsTest extends TestCase
{
    public function test_pairs_visits_into_joint_rounds_newest_first(): void
    {
        $visits = [
            (object) ['id' => 1, 'visit_number' => 1, 'player_id' => 10, 'score' => 60, 'bust' => false, 'remaining_after' => 441],
            (object) ['id' => 2, 'visit_number' => 2, 'player_id' => 20, 'score' => 80, 'bust' => false, 'remaining_after' => 421],
            (object) ['id' => 3, 'visit_number' => 3, 'player_id' => 10, 'score' => 57, 'bust' => false, 'remaining_after' => 384],
            (object) ['id' => 4, 'visit_number' => 4, 'player_id' => 20, 'score' => 0, 'bust' => true, 'remaining_before' => 421, 'remaining_after' => 421],
        ];

        $rows = H2hLegVisitRows::fromVisits($visits, 10, 20);

        $this->assertSame([
            [
                'leftThrown' => '57',
                'leftRemaining' => '384',
                'darts' => 6,
                'rightRemaining' => '421',
                'rightThrown' => 'Bust',
                'leftBust' => false,
                'rightBust' => true,
            ],
            [
                'leftThrown' => '60',
                'leftRemaining' => '441',
                'darts' => 3,
                'rightRemaining' => '421',
                'rightThrown' => '80',
                'leftBust' => false,
                'rightBust' => false,
            ],
        ], $rows);
    }
}
