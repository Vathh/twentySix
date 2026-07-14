<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthSessionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email' => 'session@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_login_issues_mobile_token_with_thirty_day_expiry(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');

        $response = $this->postJson('/api/account/login', [
            'email' => 'session@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);

        $token = PersonalAccessToken::first();
        $this->assertNotNull($token);
        $this->assertSame('mobile-app', $token->name);
        $this->assertTrue(
            $token->expires_at->equalTo(Carbon::parse('2026-08-13 12:00:00')),
        );
    }

    public function test_refresh_rotates_token_and_extends_expiry(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');

        $user = $this->verifiedUser();
        $login = $this->postJson('/api/account/login', [
            'email' => 'session@example.com',
            'password' => 'password123',
        ])->assertOk();

        $oldToken = $login->json('token');

        Carbon::setTestNow('2026-07-20 08:00:00');

        $refresh = $this->postJson('/api/account/session/refresh', [], [
            'Authorization' => 'Bearer '.$oldToken,
        ]);

        $refresh->assertOk()->assertJsonPath('user.email', 'session@example.com');

        $newToken = $refresh->json('token');
        $this->assertNotSame($oldToken, $newToken);

        $this->getJson('/api/friends', [
            'Authorization' => 'Bearer '.$oldToken,
        ])->assertUnauthorized();

        $stored = PersonalAccessToken::first();
        $this->assertTrue(
            $stored->expires_at->equalTo(Carbon::parse('2026-08-19 08:00:00')),
        );
    }

    public function test_logout_revokes_current_token(): void
    {
        $this->verifiedUser();

        $login = $this->postJson('/api/account/login', [
            'email' => 'session@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');

        $this->postJson('/api/account/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->getJson('/api/friends', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_token_is_rejected(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');

        $this->verifiedUser();
        $login = $this->postJson('/api/account/login', [
            'email' => 'session@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');

        Carbon::setTestNow('2026-08-15 00:00:00');

        $this->getJson('/api/friends', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }
}
