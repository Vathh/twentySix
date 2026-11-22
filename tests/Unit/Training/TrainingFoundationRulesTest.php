<?php

namespace Tests\Unit\Training;

use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingExerciseLimits;
use App\Domain\Training\TrainingSessionStats;
use PHPUnit\Framework\TestCase;

class TrainingFoundationRulesTest extends TestCase
{
    public function test_catalog_offers_starting_time_and_dart_count(): void
    {
        $this->assertSame([
            'loose_throws' => ['durationSeconds' => 180, 'dartCount' => null],
            'nine_darts' => ['durationSeconds' => null, 'dartCount' => 36],
            'clock_1_20_bull' => ['durationSeconds' => null, 'dartCount' => 21],
            'clock_sdt' => ['durationSeconds' => 300, 'dartCount' => null],
            'bobs_27_easy' => ['durationSeconds' => 300, 'dartCount' => null],
            'bobs_27_hard' => ['durationSeconds' => 300, 'dartCount' => null],
            'catch_20' => ['durationSeconds' => 540, 'dartCount' => null],
            'doubles_clock' => ['durationSeconds' => null, 'dartCount' => 21],
            'scoring_under_pressure' => ['durationSeconds' => 300, 'dartCount' => null],
            'scoring_65' => ['durationSeconds' => 300, 'dartCount' => null],
            'singles_under_pressure' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_41' => ['durationSeconds' => 600, 'dartCount' => null],
            'checkout_bull_81' => ['durationSeconds' => 480, 'dartCount' => null],
            'bull_counter' => ['durationSeconds' => 180, 'dartCount' => null],
        ], TrainingExerciseCatalog::all());
    }

    public function test_nine_darts_plan_slot_accepts_36_and_72_only(): void
    {
        $this->assertSame(
            ['durationSeconds' => null, 'dartCount' => 36],
            TrainingExerciseLimits::forPlanSlot('nine_darts', ['dartCount' => 36]),
        );
        $this->assertSame(
            ['durationSeconds' => null, 'dartCount' => 72],
            TrainingExerciseLimits::forPlanSlot('nine_darts', ['dartCount' => 72]),
        );

        $this->expectException(TrainingException::class);
        TrainingExerciseLimits::forPlanSlot('nine_darts', ['dartCount' => 18]);
    }

    public function test_clock_plan_slot_accepts_only_21(): void
    {
        $this->assertSame(
            ['durationSeconds' => null, 'dartCount' => 21],
            TrainingExerciseLimits::forPlanSlot('clock_1_20_bull', ['dartCount' => 21]),
        );

        $this->expectException(TrainingException::class);
        TrainingExerciseLimits::forPlanSlot('doubles_clock', ['dartCount' => 18]);
    }

    public function test_stats_keep_aggregates(): void
    {
        $stats = TrainingSessionStats::assertAggregate([
            'hits' => 7,
            'attempts' => 9,
            'percent' => 77.8,
            'stages' => [
                ['name' => '20', 'hits' => 4, 'attempts' => 9],
            ],
            'hitsBySector' => array_fill(0, 21, 1),
        ]);

        $this->assertSame(7, $stats['hits']);
        $this->assertSame(77.8, $stats['percent']);
    }

    public function test_stats_reject_a_dart_list(): void
    {
        $this->expectException(TrainingException::class);
        $this->expectExceptionMessage('listy lotek');

        TrainingSessionStats::assertAggregate([
            'darts' => [20, 19, 18],
        ]);
    }

    public function test_stats_reject_a_single_dart_shape(): void
    {
        $this->expectException(TrainingException::class);

        TrainingSessionStats::assertAggregate([
            'rows' => [
                ['sector' => 20, 'ring' => 'triple'],
            ],
        ]);
    }

    public function test_stats_reject_a_long_sequence(): void
    {
        $this->expectException(TrainingException::class);

        TrainingSessionStats::assertAggregate([
            'sequence' => array_fill(0, 33, 1),
        ]);
    }
}
