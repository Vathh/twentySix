<?php

namespace Tests\Unit\GameScoring;

use App\Support\GameScoring\VisitRecorder;
use DomainException;
use PHPUnit\Framework\TestCase;

class VisitRecorderTest extends TestCase
{
    public function test_is_visit_complete(): void
    {
        $this->assertTrue(VisitRecorder::isVisitComplete(true, false, 1));
        $this->assertTrue(VisitRecorder::isVisitComplete(false, true, 1));
        $this->assertTrue(VisitRecorder::isVisitComplete(false, false, 3));
        $this->assertFalse(VisitRecorder::isVisitComplete(false, false, 2));
    }

    public function test_compute_remaining_after(): void
    {
        $this->assertSame(441, VisitRecorder::computeRemainingAfter(501, 60, false, false));
        $this->assertSame(501, VisitRecorder::computeRemainingAfter(501, 60, true, false));
        $this->assertSame(0, VisitRecorder::computeRemainingAfter(141, 141, false, true));
    }

    public function test_validate_accepts_normal_visit(): void
    {
        VisitRecorder::validate(
            remainingBefore: 501,
            score: 60,
            remainingAfter: 441,
            dartsInVisit: 3,
            closedLeg: false,
            bust: false,
        );

        $this->addToAssertionCount(1);
    }

    public function test_validate_accepts_checkout(): void
    {
        VisitRecorder::validate(
            remainingBefore: 141,
            score: 141,
            remainingAfter: 0,
            dartsInVisit: 3,
            closedLeg: true,
            bust: false,
        );

        $this->addToAssertionCount(1);
    }

    public function test_validate_accepts_bust(): void
    {
        VisitRecorder::validate(
            remainingBefore: 32,
            score: 0,
            remainingAfter: 32,
            dartsInVisit: 2,
            closedLeg: false,
            bust: true,
        );

        $this->addToAssertionCount(1);
    }

    public function test_validate_rejects_inconsistent_remaining(): void
    {
        $this->expectException(DomainException::class);

        VisitRecorder::validate(
            remainingBefore: 501,
            score: 60,
            remainingAfter: 440,
            dartsInVisit: 3,
            closedLeg: false,
            bust: false,
        );
    }

    public function test_validate_rejects_bust_with_score(): void
    {
        $this->expectException(DomainException::class);

        VisitRecorder::validate(
            remainingBefore: 32,
            score: 10,
            remainingAfter: 32,
            dartsInVisit: 2,
            closedLeg: false,
            bust: true,
        );
    }

    public function test_current_player_index_from_visits(): void
    {
        $playerIds = [10, 20];
        $visits = [
            ['playerId' => 10, 'score' => 60, 'dartsInVisit' => 3, 'bust' => false, 'closedLeg' => false, 'visitNumber' => 1],
        ];

        $this->assertSame(1, VisitRecorder::currentPlayerIndexFromVisits($visits, $playerIds));

        $partial = [
            ['playerId' => 10, 'score' => 40, 'dartsInVisit' => 2, 'bust' => false, 'closedLeg' => false, 'visitNumber' => 1],
        ];
        $this->assertSame(0, VisitRecorder::currentPlayerIndexFromVisits($partial, $playerIds));

        $bust = [
            ['playerId' => 10, 'score' => 0, 'dartsInVisit' => 3, 'bust' => true, 'closedLeg' => false, 'visitNumber' => 1],
        ];
        $this->assertSame(0, VisitRecorder::currentPlayerIndexFromVisits($bust, $playerIds));
    }

    public function test_remaining_from_leg_visits(): void
    {
        $visits = [
            (object) [
                'player_id' => 1,
                'score' => 60,
                'remaining_before' => 501,
                'remaining_after' => 441,
                'darts_in_visit' => 3,
                'closed_leg' => false,
                'bust' => false,
                'visit_number' => 1,
                'id' => 1,
            ],
        ];

        $this->assertSame(441, VisitRecorder::remainingFromLegVisits($visits, 501));
        $this->assertSame(501, VisitRecorder::remainingFromLegVisits([], 501));
    }

    public function test_leg_winner_and_legs_won(): void
    {
        $visits = [
            (object) [
                'player_id' => 10,
                'score' => 141,
                'remaining_before' => 141,
                'remaining_after' => 0,
                'darts_in_visit' => 3,
                'closed_leg' => true,
                'bust' => false,
                'visit_number' => 5,
                'leg_number' => 1,
                'id' => 5,
            ],
            (object) [
                'player_id' => 20,
                'score' => 141,
                'remaining_before' => 141,
                'remaining_after' => 0,
                'darts_in_visit' => 3,
                'closed_leg' => true,
                'bust' => false,
                'visit_number' => 4,
                'leg_number' => 2,
                'id' => 6,
            ],
        ];

        $this->assertSame(10, VisitRecorder::legWinnerPlayerId(collect($visits)->where('leg_number', 1)));
        $legsWon = VisitRecorder::countLegsWon($visits, [10, 20]);
        $this->assertSame(1, $legsWon[10]);
        $this->assertSame(1, $legsWon[20]);
    }
}
