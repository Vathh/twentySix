<?php

namespace Tests\Feature;

use App\Models\Training\TrainingHistoryWipe;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use App\Services\Training\TrainingExerciseCountService;
use App\Services\Training\TrainingHistoryWipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingHistoryWipeTest extends TestCase
{
    use RefreshDatabase;

    public function test_wipe_hides_sessions_from_history_and_lowers_global_count(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'bull_counter',
                'completedAt' => now()->toIso8601String(),
                'score' => 10,
                'stats' => ['hits' => 2, 'attempts' => 5],
            ])
            ->assertCreated();

        $this->assertSame(1, app(TrainingExerciseCountService::class)->globalTotal());

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/analysis')
            ->assertOk()
            ->assertJsonPath('completedExerciseCount', 1);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/history')
            ->assertOk()
            ->assertJsonPath('wiped', true);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->assertSame(0, app(TrainingExerciseCountService::class)->globalTotal());
        $this->assertSame(1, TrainingSession::withoutGlobalScopes()->count());
        $this->assertDatabaseCount('training_history_wipes', 1);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/analysis')
            ->assertOk()
            ->assertJsonPath('completedExerciseCount', 0);
    }

    public function test_restore_and_purge_commands(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'loose_throws',
                'completedAt' => now()->toIso8601String(),
                'score' => null,
                'stats' => ['elapsedSeconds' => 180],
            ])
            ->assertCreated();

        $this->actingAs($player, 'sanctum')->deleteJson('/api/training/history')->assertOk();

        $this->artisan('training:restore-history', ['userId' => $player->id])
            ->assertSuccessful();

        $this->assertSame(1, app(TrainingExerciseCountService::class)->forUser($player));
        $this->assertNull(TrainingSession::query()->first()?->wipe_id);

        $this->actingAs($player, 'sanctum')->deleteJson('/api/training/history')->assertOk();

        $wipe = TrainingHistoryWipe::query()->whereNull('restored_at')->latest('id')->first();
        $this->assertNotNull($wipe);
        $wipe->forceFill(['purge_after' => now()->subMinute()])->save();

        $this->artisan('training:purge-history')->assertSuccessful();
        $this->assertSame(0, TrainingSession::withoutGlobalScopes()->count());
        $this->assertDatabaseCount('training_history_wipes', 1); // restored wipe remains
        $this->assertSame(0, TrainingHistoryWipe::query()->whereNull('restored_at')->count());
    }

    public function test_account_deletion_force_wipes_training(): void
    {
        $player = User::factory()->create([
            'password' => bcrypt('secret-pass'),
        ]);
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'exerciseKey' => 'bull_counter',
                'completedAt' => now()->toIso8601String(),
                'score' => 4,
                'stats' => ['hits' => 1, 'attempts' => 3],
            ])
            ->assertCreated();

        $this->actingAs($player, 'sanctum')->deleteJson('/api/training/history')->assertOk();
        $this->assertSame(1, TrainingSession::withoutGlobalScopes()->count());

        app(TrainingHistoryWipeService::class)->forceDeleteForAccount($player);

        $this->assertSame(0, TrainingSession::withoutGlobalScopes()->count());
        $this->assertSame(0, TrainingHistoryWipe::query()->where('user_id', $player->id)->count());
    }
}
