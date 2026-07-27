<?php

namespace Tests\Unit\QuickGameFfa;

use App\Support\QuickGameFfa\CricketRules;
use PHPUnit\Framework\TestCase;

class CricketRulesTest extends TestCase
{
    public function test_triple_closes_and_scores(): void
    {
        $p0 = CricketRules::emptyHits();
        $p0['20'] = 2;
        $hits = [
            0 => $p0,
            1 => CricketRules::emptyHits(),
        ];
        $result = CricketRules::applyDart($hits, 0, 20, 3);
        $this->assertSame(5, $result['hits']['20']);
        $this->assertSame(40, $result['pointsScored']);
    }

    public function test_dead_number_no_points(): void
    {
        $p0 = CricketRules::emptyHits();
        $p0['20'] = 3;
        $p1 = CricketRules::emptyHits();
        $p1['20'] = 3;
        $hits = [
            0 => $p0,
            1 => $p1,
        ];
        $result = CricketRules::applyDart($hits, 0, 20, 1);
        $this->assertSame(0, $result['pointsScored']);
        $this->assertSame(3, $result['hits']['20']);
    }

    public function test_leg_winner_requires_strict_lead(): void
    {
        $closed = [];
        foreach (CricketRules::SEGMENTS as $seg) {
            $closed[CricketRules::segmentKey($seg)] = 3;
        }

        $tied = [
            ['hits' => $closed, 'points' => 40],
            ['hits' => $closed, 'points' => 40],
        ];
        $this->assertNull(CricketRules::findLegWinnerIndex($tied));

        $lead = [
            ['hits' => $closed, 'points' => 41],
            ['hits' => $closed, 'points' => 40],
        ];
        $this->assertSame(0, CricketRules::findLegWinnerIndex($lead));
    }
}
