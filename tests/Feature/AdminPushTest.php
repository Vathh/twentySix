<?php

namespace Tests\Feature;

use App\Jobs\SendAdminPushJob;
use App\Models\Push\UserPushToken;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_broadcast_and_self_test(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $player = User::factory()->create();

        UserPushToken::query()->create([
            'user_id' => $player->id,
            'expo_push_token' => 'ExponentPushToken[player]',
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
        UserPushToken::query()->create([
            'user_id' => $admin->id,
            'expo_push_token' => 'ExponentPushToken[admin]',
            'platform' => 'ios',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.push.store'), [
                'title' => 'Hello',
                'body' => 'World',
                'mode' => 'all',
                'confirm' => '1',
            ])
            ->assertRedirect(route('admin.push.index'));

        $this->assertDatabaseHas('admin_push_dispatches', [
            'admin_id' => $admin->id,
            'title' => 'Hello',
            'audience' => 'all_with_token',
            'recipient_count' => 2,
            'test_only' => 0,
        ]);

        Queue::assertPushed(SendAdminPushJob::class, function (SendAdminPushJob $job): bool {
            return count($job->tokens) === 2 && $job->title === 'Hello';
        });

        $this->actingAs($admin)
            ->post(route('admin.push.store'), [
                'title' => 'Test',
                'body' => 'Only me',
                'mode' => 'self',
                'confirm' => '1',
            ])
            ->assertRedirect(route('admin.push.index'));

        $this->assertDatabaseHas('admin_push_dispatches', [
            'title' => 'Test',
            'audience' => 'self_test',
            'recipient_count' => 1,
            'test_only' => 1,
        ]);
    }

    public function test_player_cannot_open_push_panel(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)
            ->get(route('admin.push.index'))
            ->assertForbidden();
    }
}
