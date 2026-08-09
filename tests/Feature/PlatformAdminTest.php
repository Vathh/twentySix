<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makePlatformAdmin(): User
    {
        $user = User::factory()->create([
            'email' => 'owner@test.com',
        ]);
        $user->forceFill([
            'role' => 'admin',
            'can_create_leagues' => true,
        ])->save();

        Player::create([
            'user_id' => $user->id,
            'name' => 'Owner',
        ]);

        return $user->fresh();
    }

    private function makeRegularUser(string $email = 'player@test.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        Player::create([
            'user_id' => $user->id,
            'name' => 'Regular Player',
        ]);

        return $user->fresh();
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $user = $this->makeRegularUser();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_platform_admin_sees_dashboard(): void
    {
        $this->withoutVite();
        $admin = $this->makePlatformAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Panel platformy')
            ->assertSee('Użytkownicy');
    }

    public function test_platform_admin_can_toggle_can_create_leagues(): void
    {
        $admin = $this->makePlatformAdmin();
        $target = $this->makeRegularUser();

        $this->assertFalse((bool) $target->can_create_leagues);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->post("/admin/users/{$target->id}/can-create-leagues", [
                'can_create_leagues' => 1,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        $this->assertTrue((bool) $target->fresh()->can_create_leagues);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->post("/admin/users/{$target->id}/can-create-leagues", [
                'can_create_leagues' => 0,
            ])
            ->assertRedirect('/admin/users');

        $this->assertFalse((bool) $target->fresh()->can_create_leagues);
    }

    public function test_platform_admin_users_search_by_email(): void
    {
        $this->withoutVite();
        $admin = $this->makePlatformAdmin();
        $this->makeRegularUser('szukany@example.com');
        $this->makeRegularUser('inny@example.com');

        $this->actingAs($admin)
            ->get('/admin/users?q=szukany')
            ->assertOk()
            ->assertSee('szukany@example.com')
            ->assertDontSee('inny@example.com');
    }
}
