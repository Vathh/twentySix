<?php

namespace Tests\Unit\Training;

use App\Domain\Training\TrainingCompletedExerciseCount;
use App\Domain\Training\TrainingKind;
use PHPUnit\Framework\TestCase;

class TrainingCompletedExerciseCountTest extends TestCase
{
    public function test_exercise_counts_as_one(): void
    {
        $this->assertSame(1, TrainingCompletedExerciseCount::forKind(TrainingKind::Exercise, []));
    }

    public function test_plan_counts_completed_exercises_only(): void
    {
        $stats = [
            'items' => [
                ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'loose_throws'],
                ['kind' => 'break', 'status' => 'completed'],
                ['kind' => 'exercise', 'status' => 'skipped', 'exerciseKey' => 'clock_1_20_bull'],
                ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'bull_counter'],
            ],
        ];

        $this->assertSame(2, TrainingCompletedExerciseCount::forKind(TrainingKind::Plan, $stats));
        $this->assertSame(2, TrainingCompletedExerciseCount::forKind(TrainingKind::Event, $stats));
    }

    public function test_challenge_counts_stages(): void
    {
        $stats = [
            'stages' => [
                ['id' => 'a', 'score' => 1],
                ['id' => 'b', 'score' => 2],
                ['id' => 'c', 'score' => 3],
            ],
        ];

        $this->assertSame(3, TrainingCompletedExerciseCount::forKind(TrainingKind::Challenge, $stats));
        $this->assertSame(1, TrainingCompletedExerciseCount::forKind(TrainingKind::Challenge, []));
    }
}
