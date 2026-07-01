<?php

namespace Tests\Feature;

use App\Models\Users\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApiAuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_register_sends_verification_and_does_not_return_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Mobile User',
            'email' => 'mobile@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('email', 'mobile@example.com')
            ->assertJsonMissing(['token']);

        $user = User::where('email', 'mobile@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('players', [
            'name' => 'Mobile User',
            'user_id' => $user->id,
        ]);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_api_login_rejects_unverified_user(): void
    {
        User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/account/login', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Potwierdź adres email — sprawdź skrzynkę (link z rejestracji).');
    }

    public function test_api_login_succeeds_after_email_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'verified-flow@example.com',
            'password' => Hash::make('password123'),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($verificationUrl)->assertRedirect(route('pages.loginPanel'));

        $this->postJson('/api/account/login', [
            'email' => 'verified-flow@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_api_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'resend@example.com',
        ]);

        $this->postJson('/api/email/verification-notification', [
            'email' => 'resend@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
