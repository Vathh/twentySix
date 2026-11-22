<?php

namespace Tests\Unit\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Services\Training\TrainingActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_items_count_as_separate_exercises_on_day(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::now('UTC')->startOfDay();

        TrainingSession::query()->create([
            'user_id' => $user->id,
            'client_session_id' => (string) Str::uuid(),
            'definition_id' => null,
            'exercise_key' => null,
            'kind' => TrainingKind::Plan,
            'completed_at' => $today->setTime(15, 0),
            'score' => 10,
            'stats' => [
                'items' => [
                    ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'clock_1_20_bull', 'score' => 1],
                    ['kind' => 'break', 'status' => 'completed'],
                    ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'bull_counter', 'score' => 2],
                    ['kind' => 'exercise', 'status' => 'skipped', 'exerciseKey' => 'bobs_27_easy', 'score' => null],
                    ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'catch_20', 'score' => 3],
                    ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'scoring_65', 'score' => 4],
                    ['kind' => 'exercise', 'status' => 'completed', 'exerciseKey' => 'singles_under_pressure', 'score' => 5],
                ],
            ],
        ]);

        TrainingSession::query()->create([
            'user_id' => $user->id,
            'client_session_id' => (string) Str::uuid(),
            'definition_id' => null,
            'exercise_key' => 'loose_throws',
            'kind' => TrainingKind::Exercise,
            'completed_at' => $today->setTime(18, 0),
            'score' => null,
            'stats' => ['elapsedSeconds' => 180],
        ]);

        $activity = app(TrainingActivityService::class)->forUser($user, 30);

        $this->assertSame(30, $activity['days']);
        $this->assertCount(30, $activity['points']);
        $todayRow = collect($activity['points'])->firstWhere('date', $today->toDateString());
        $this->assertNotNull($todayRow);
        // 5 completed plan exercises + 1 solo exercise
        $this->assertSame(6, $todayRow['count']);
    }
}
