<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_upsert_push_token(): void
    {
        $this->putJson('/api/push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
        ])->assertUnauthorized();
    }

    public function test_user_can_upsert_and_delete_push_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
            'deviceName' => 'Pixel',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Token push zapisany']);

        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'expo_push_token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
            'device_name' => 'Pixel',
        ]);

        // Upsert przenosi token na innego usera (np. reinstall / zmiana konta)
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->putJson('/api/push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $other->id,
            'expo_push_token' => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
        ]);
        $this->assertDatabaseMissing('user_push_tokens', [
            'user_id' => $user->id,
            'expo_push_token' => 'ExponentPushToken[abc123]',
        ]);

        $this->deleteJson('/api/push-tokens', [
            'token' => 'ExponentPushToken[abc123]',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Token push usunięty']);

        $this->assertDatabaseMissing('user_push_tokens', [
            'expo_push_token' => 'ExponentPushToken[abc123]',
        ]);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/push-tokens', [
            'token' => 'not-a-valid-token',
            'platform' => 'android',
        ])->assertStatus(422);
    }
}
