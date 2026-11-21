<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingEventAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_complete_and_abort_consume_window_slot(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $eventId = $this->createActiveEvent($admin);

        $clientA = (string) Str::uuid();
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/events/'.$eventId.'/attempts', [
                'clientAttemptId' => $clientA,
            ])
            ->assertCreated()
            ->assertJsonPath('attempt.status', 'in_progress');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/events/'.$eventId.'/attempts', [
                'clientAttemptId' => (string) Str::uuid(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'training_event_attempt_open');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/events/'.$eventId.'/attempts/'.$clientA.'/abort')
            ->assertOk()
            ->assertJsonPath('attempt.status', 'voided');

        $clientB = (string) Str::uuid();
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/events/'.$eventId.'/attempts', [
                'clientAttemptId' => $clientB,
            ])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'training_event_window_exhausted');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function createActiveEvent(User $admin): int
    {
        $id = (int) $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'event',
                'name' => 'Sierpień',
                'startsAt' => now()->subHour()->toIso8601String(),
                'endsAt' => now()->addDays(7)->toIso8601String(),
                'attemptsPerWindow' => 1,
                'windowMode' => 'calendar_week',
                'attemptTimeoutHours' => 4,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws', 'scored' => false],
                    ['kind' => 'exercise', 'exerciseKey' => 'clock_1_20_bull', 'scored' => true],
                ],
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertOk();

        return $id;
    }
}
