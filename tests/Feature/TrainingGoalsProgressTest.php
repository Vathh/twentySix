<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingGoalsProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_65_moves_scoring_goal_and_wipe_resets(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'scoring_65',
                'completedAt' => now()->toIso8601String(),
                'score' => 3,
                'stats' => [
                    'total' => 300,
                    'approaches' => 4,
                    'visitsAtLeast60' => 3,
                    'average' => 75,
                    'inputMode' => 'sum',
                ],
            ])
            ->assertCreated();

        $goals = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/goals')
            ->assertOk()
            ->json('goals');

        $scoring = collect($goals)->firstWhere('id', 'scoring');
        $this->assertNotNull($scoring);
        $this->assertSame(75.0, (float) $scoring['value']);
        $this->assertGreaterThan(0, $scoring['completedThresholds']);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/analysis')
            ->assertOk()
            ->assertJsonPath('goals.0.id', 'scoring')
            ->assertJsonPath('completedExerciseCount', 1);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/history')
            ->assertOk();

        $after = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/goals')
            ->assertOk()
            ->json('goals');
        $scoringAfter = collect($after)->firstWhere('id', 'scoring');
        $this->assertSame(0.0, (float) $scoringAfter['value']);
        $this->assertSame(0, $scoringAfter['completedThresholds']);
    }

    public function test_doubles_and_checkouts_from_catch20_stats(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'catch_20',
                'completedAt' => now()->toIso8601String(),
                'score' => 5,
                'stats' => [
                    'closed' => 5,
                    'checkoutsClosed' => 5,
                    'checkoutAttempts' => 20,
                    'inputMode' => 'per_dart',
                    'doubles' => [
                        ['label' => 'D10', 'attempts' => 10, 'hits' => 4],
                        ['label' => 'D20', 'attempts' => 10, 'hits' => 2],
                    ],
                ],
            ])
            ->assertCreated();

        $goals = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/goals')
            ->assertOk()
            ->json();

        $doubles = collect($goals['goals'])->firstWhere('id', 'doubles');
        $checkouts = collect($goals['goals'])->firstWhere('id', 'checkouts');
        $this->assertSame(30.0, (float) $doubles['value']);
        $this->assertSame(25.0, (float) $checkouts['value']);
        $this->assertTrue($doubles['assessmentReady']);
        $this->assertTrue($checkouts['assessmentReady']);
    }

    public function test_regularity_counts_sessions_in_last_30_days(): void
    {
        $player = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($player, 'sanctum')
                ->postJson('/api/training/sessions', [
                    'clientSessionId' => (string) Str::uuid(),
                    'exerciseKey' => 'loose_throws',
                    'completedAt' => now()->subDays($i)->toIso8601String(),
                    'score' => null,
                    'stats' => ['elapsedSeconds' => 180],
                ])
                ->assertCreated();
        }

        $goals = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/goals')
            ->assertOk()
            ->json('goals');
        $regularity = collect($goals)->firstWhere('id', 'regularity');
        $this->assertSame(5, $regularity['value']);
        $this->assertSame(1, $regularity['completedThresholds']);
    }
}
