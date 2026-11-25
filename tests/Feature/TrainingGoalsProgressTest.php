<?php

namespace Tests\Feature;

use App\Enums\CareerSource;
use App\Models\Career\PlayerGameSnapshot;
use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingGoalsProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_is_slim_and_medals_endpoint_works(): void
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

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/analysis')
            ->assertOk()
            ->assertJsonPath('completedExerciseCount', 1)
            ->assertJsonPath('activity.days', 90)
            ->assertJsonCount(90, 'activity.points')
            ->assertJsonMissingPath('goals')
            ->assertJsonMissingPath('badges')
            ->assertJsonMissingPath('records')
            ->assertJsonCount(1, 'medals');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/medals')
            ->assertOk()
            ->assertJsonCount(1, 'medals')
            ->assertJsonPath('medals.0.placeholder', true);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/history')
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/analysis')
            ->assertOk()
            ->assertJsonPath('completedExerciseCount', 0);
    }

    public function test_skill_stats_merge_session_scoring_and_support_offset(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'scoring_65',
                'completedAt' => now()->subDays(10)->toIso8601String(),
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

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/skill-stats?window=90d&offset=0')
            ->assertOk()
            ->assertJsonPath('window', '90d')
            ->assertJsonPath('offset', 0)
            ->assertJsonPath('hero.hasX01', true)
            ->assertJsonPath('hero.x01Average', 75)
            ->assertJsonPath('canGoForward', false);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/skill-stats?window=90d&offset=1')
            ->assertOk()
            ->assertJsonPath('offset', 1)
            ->assertJsonPath('canGoForward', true)
            ->assertJsonPath('hero.hasX01', false)
            ->assertJsonCount(0, 'series.x01_average');
    }

    public function test_skill_stats_include_training_career_snapshot(): void
    {
        $user = User::factory()->create();
        $linked = Player::create(['name' => 'Trenujący', 'user_id' => $user->id]);

        PlayerGameSnapshot::query()->create([
            'player_id' => $linked->id,
            'source' => CareerSource::Training,
            'game_type' => 'x01',
            'occurred_at' => now()->subDays(2),
            'client_uuid' => (string) Str::uuid(),
            'sourceable_type' => 'training_game',
            'sourceable_id' => 1,
            'metrics' => [
                'darts_thrown' => 99,
                'points' => 1650,
                'average' => 50,
                'double_tracked' => true,
                'double_attempts' => 10,
                'double_successes' => 4,
            ],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/training/skill-stats?window=90d')
            ->assertOk()
            ->assertJsonPath('hero.x01Average', 50)
            ->assertJsonPath('hero.doublePct', 40)
            ->assertJsonPath('hero.doubleLabel', '4/10')
            ->assertJsonCount(1, 'series.x01_average')
            ->assertJsonCount(1, 'series.double_pct');
    }

    public function test_sector_stats_aggregate_aims_in_30_day_window(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'catch_20',
                'completedAt' => now()->subDays(5)->toIso8601String(),
                'score' => 5,
                'stats' => [
                    'closed' => 5,
                    'checkoutsClosed' => 5,
                    'checkoutAttempts' => 20,
                    'inputMode' => 'per_dart',
                    'aims' => [
                        ['aim' => 'D10', 'attempts' => 10, 'hits' => 4],
                        ['aim' => 'D20', 'attempts' => 10, 'hits' => 2],
                    ],
                ],
            ])
            ->assertCreated();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'catch_20',
                'completedAt' => now()->subDays(40)->toIso8601String(),
                'score' => 1,
                'stats' => [
                    'closed' => 1,
                    'checkoutsClosed' => 1,
                    'checkoutAttempts' => 5,
                    'inputMode' => 'per_dart',
                    'aims' => [
                        ['aim' => 'D10', 'attempts' => 5, 'hits' => 5],
                    ],
                ],
            ])
            ->assertCreated();

        $payload = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sector-stats?offset=0')
            ->assertOk()
            ->assertJsonPath('window', '30d')
            ->assertJsonPath('offset', 0)
            ->json();

        $byAim = collect($payload['aims'])->keyBy('aim');
        $this->assertSame(10, $byAim['D10']['attempts']);
        $this->assertSame(4, $byAim['D10']['hits']);
        $this->assertSame(10, $byAim['D20']['attempts']);
        $this->assertTrue($payload['canGoBack']);

        $prev = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sector-stats?offset=1')
            ->assertOk()
            ->assertJsonPath('offset', 1)
            ->assertJsonPath('canGoForward', true)
            ->json();

        $prevByAim = collect($prev['aims'])->keyBy('aim');
        $this->assertSame(5, $prevByAim['D10']['attempts']);
        $this->assertSame(5, $prevByAim['D10']['hits']);
        $this->assertArrayNotHasKey('D20', $prevByAim->all());
    }
}
