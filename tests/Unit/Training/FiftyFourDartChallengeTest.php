<?php

namespace Tests\Unit\Training;

use App\Domain\Training\FiftyFourDartChallenge;
use PHPUnit\Framework\TestCase;

class FiftyFourDartChallengeTest extends TestCase
{
    public function test_total_darts_is_fifty_four(): void
    {
        $this->assertSame(54, FiftyFourDartChallenge::totalDarts());
    }

    public function test_shanghai_visit_scores_set_bonus(): void
    {
        $darts = [];
        foreach (['10', '11', '12', '13', '14', '15'] as $sector) {
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'single'];
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'double'];
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'triple'];
        }
        $result = FiftyFourDartChallenge::score($darts);
        $this->assertSame(1140, $result['stages'][0]['score']);
    }

    public function test_perfect_challenge_is_3190(): void
    {
        $darts = [];
        foreach (['10', '11', '12', '13', '14', '15'] as $sector) {
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'single'];
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'double'];
            $darts[] = ['stageId' => 'shanghai_10_15', 'target' => $sector, 'ring' => 'triple'];
        }
        for ($n = 1; $n <= 20; $n++) {
            $darts[] = ['stageId' => 'doubles', 'target' => 'D'.$n, 'ring' => 'double'];
        }
        $darts[] = ['stageId' => 'doubles', 'target' => 'Bull', 'ring' => 'inner'];
        foreach (['16', '17', '18', '19', '20'] as $sector) {
            $darts[] = ['stageId' => 'shanghai_16_20', 'target' => $sector, 'ring' => 'single'];
            $darts[] = ['stageId' => 'shanghai_16_20', 'target' => $sector, 'ring' => 'double'];
            $darts[] = ['stageId' => 'shanghai_16_20', 'target' => $sector, 'ring' => 'triple'];
        }

        $result = FiftyFourDartChallenge::score($darts);
        $this->assertTrue($result['complete']);
        $this->assertSame(3190, $result['score']);
        $this->assertSame(1140, $result['stages'][0]['score']);
        $this->assertSame(1100, $result['stages'][1]['score']);
        $this->assertSame(950, $result['stages'][2]['score']);
    }

    public function test_stage_order_is_shanghai_doubles_shanghai(): void
    {
        $ids = array_column(FiftyFourDartChallenge::stages(), 'id');
        $this->assertSame(['shanghai_10_15', 'doubles', 'shanghai_16_20'], $ids);
    }
}
