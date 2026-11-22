<?php

namespace Tests\Unit\Training;

use App\Domain\Training\TrainingMetricExtractor;
use PHPUnit\Framework\TestCase;

class TrainingMetricExtractorTest extends TestCase
{
    public function test_scoring_65_extracts_average_inputs(): void
    {
        $m = TrainingMetricExtractor::fromExercise('scoring_65', [
            'total' => 200,
            'approaches' => 4,
            'visitsAtLeast60' => 2,
            'average' => 50,
        ]);

        $this->assertSame(200, $m['scoringTotal']);
        $this->assertSame(4, $m['scoringApproaches']);
        $this->assertSame(2, $m['scoringVisitsAtLeast60']);
    }

    public function test_scoring_under_pressure_does_not_feed_scoring_numbers(): void
    {
        $m = TrainingMetricExtractor::fromExercise('scoring_under_pressure', [
            'hits' => 10,
            'attempts' => 30,
            'aims' => [['aim' => '20', 'attempts' => 10, 'hits' => 5]],
        ]);

        $this->assertSame(0, $m['scoringTotal']);
        $this->assertSame(0, $m['scoringApproaches']);
    }

    public function test_doubles_from_clock_and_bobs_and_catch20(): void
    {
        $clock = TrainingMetricExtractor::fromExercise('doubles_clock', [
            'hits' => 15,
            'attempts' => 21,
        ]);
        $this->assertSame(21, $clock['doublesAttempts']);
        $this->assertSame(15, $clock['doublesHits']);

        $bobs = TrainingMetricExtractor::fromExercise('bobs_27_easy', [
            'aims' => [
                ['aim' => 'D1', 'attempts' => 3, 'hits' => 1],
                ['aim' => 'D2', 'attempts' => 3, 'hits' => 2],
            ],
        ]);
        $this->assertSame(6, $bobs['doublesAttempts']);
        $this->assertSame(3, $bobs['doublesHits']);

        $catch = TrainingMetricExtractor::fromExercise('catch_20', [
            'closed' => 4,
            'checkoutsClosed' => 4,
            'checkoutAttempts' => 10,
            'aims' => [
                ['aim' => 'D13', 'attempts' => 2, 'hits' => 1],
            ],
        ]);
        $this->assertSame(2, $catch['doublesAttempts']);
        $this->assertSame(1, $catch['doublesHits']);
        $this->assertSame(4, $catch['checkoutsClosed']);
        $this->assertSame(10, $catch['checkoutAttempts']);

        $legacy = TrainingMetricExtractor::fromExercise('catch_20', [
            'doubles' => [
                ['label' => 'D13', 'attempts' => 2, 'hits' => 1],
            ],
        ]);
        $this->assertSame(2, $legacy['doublesAttempts']);
        $this->assertSame(1, $legacy['doublesHits']);
    }

    public function test_bull_81_feeds_inner_doubles_and_checkouts(): void
    {
        $m = TrainingMetricExtractor::fromExercise('checkout_bull_81', [
            'aims' => [['aim' => 'Inner', 'attempts' => 5, 'hits' => 2]],
            'checkoutsClosed' => 2,
            'checkoutAttempts' => 5,
        ]);
        $this->assertSame(5, $m['doublesAttempts']);
        $this->assertSame(2, $m['doublesHits']);
        $this->assertSame(2, $m['checkoutsClosed']);
        $this->assertSame(5, $m['checkoutAttempts']);
    }

    public function test_unmapped_exercise_is_empty(): void
    {
        $m = TrainingMetricExtractor::fromExercise('loose_throws', [
            'elapsedSeconds' => 180,
        ]);
        $this->assertSame(0, $m['scoringApproaches']);
        $this->assertSame(0, $m['doublesAttempts']);
        $this->assertSame(0, $m['checkoutAttempts']);
    }
}
