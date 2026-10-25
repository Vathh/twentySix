<?php

namespace Tests\Feature;

use App\Domain\Auth\PasswordResetDomain;
use App\Models\Users\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_email_gets_the_same_message_and_no_mail(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success', PasswordResetDomain::SENT_MESSAGE);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_known_email_sends_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success', PasswordResetDomain::SENT_MESSAGE);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_banned_account_gets_the_same_message_and_no_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['banned_at' => now()]);

        $this->postJson('/api/account/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', PasswordResetDomain::SENT_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_reset_changes_password_and_revokes_api_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $user->createToken('phone');

        $token = $this->captureResetToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertRedirect(route('pages.loginPanel'))
            ->assertSessionHas('success', PasswordResetDomain::RESET_MESSAGE);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->postJson('/api/account/login', [
            'email' => $user->email,
            'password' => 'oldpassword',
        ])->assertUnauthorized();
    }

    public function test_expired_token_does_not_change_password(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $this->captureResetToken($user);

        $this->travel(61)->minutes();

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'email' => $user->email,
                'token' => $token,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword', $user->password));
    }

    public function test_guest_can_open_both_forms(): void
    {
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@b.c']))->assertOk();
    }

    private function captureResetToken(User $user): string
    {
        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);

        return $token;
    }
}
