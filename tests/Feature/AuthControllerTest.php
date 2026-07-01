<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_must_verify_email_before_login(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('registered_email', 'test@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertDatabaseHas('players', [
            'name' => 'Test User',
            'user_id' => $user->id,
        ]);

        $this->assertFalse(Auth::check());
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('pages.loginPanel'))
            ->assertSessionHas('success');

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertRedirect('/');

        $this->assertTrue(Auth::check());
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_cannot_register_with_short_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_cannot_register_without_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('credentials');
        $this->assertFalse(Auth::check());
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('credentials');
        $this->assertFalse(Auth::check());
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }

    public function test_logout_regenerates_session_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $oldToken = session()->token();

        $this->post('/logout');

        $this->assertNotEquals($oldToken, session()->token());
    }
}
