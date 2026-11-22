<?php

namespace Tests\Feature;

use App\Models\Training\TrainingDefinition;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_player_do_not_enter_training_admin(): void
    {
        $this->get(route('admin.training.index'))->assertRedirect('/login');

        $player = User::factory()->create();

        $this->actingAs($player)
            ->get(route('admin.training.index'))
            ->assertForbidden();

        $this->actingAs($player)
            ->post(route('admin.training.store', 'plans'), [
                'name' => 'Wieczór',
                'difficulty' => 'easy',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'loose_throws'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('training_definitions', 0);
    }

    public function test_exercise_list_is_fixed_and_a_plan_can_change_its_variable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.training.list', 'exercises'))
            ->assertOk()
            ->assertSee('Luźne rzuty')
            ->assertSee('180 s')
            ->assertSee('9/18 lotek')
            ->assertSee('36 lotek')
            ->assertDontSee('>Nowe<', false);

        $this->actingAs($admin)
            ->get(route('admin.training.create', 'exercises'))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.training.store', 'plans'), [
                'name' => 'Wieczór',
                'difficulty' => 'medium',
                'declared_minutes' => 60,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'loose_throws', 'duration_seconds' => 240],
                    ['kind' => 'exercise', 'exercise_key' => 'nine_darts'],
                ],
            ])
            ->assertRedirect();

        $id = $this->idOf('Wieczór');

        $this->actingAs($admin)
            ->get(route('admin.training.show', $id))
            ->assertOk()
            ->assertSee('Luźne rzuty')
            ->assertSee('240 s')
            ->assertSee('9/18 lotek')
            ->assertSee('36 lotek')
            ->assertSee('Nieaktywne');

        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $id,
            'exercise_key' => 'loose_throws',
            'duration_seconds' => 240,
            'dart_count' => null,
        ]);
        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $id,
            'exercise_key' => 'nine_darts',
            'dart_count' => 36,
        ]);
    }

    public function test_admin_stores_a_graphic_or_keeps_initials(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.training.store', 'plans'), [
                'name' => 'Zegar',
                'difficulty' => 'medium',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'clock_sdt'],
                ],
                'image' => UploadedFile::fake()->image('cover.png', 800, 400),
            ])
            ->assertRedirect();

        $id = $this->idOf('Zegar');
        $path = TrainingDefinition::query()->findOrFail($id)->image_path;
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)
            ->put(route('admin.training.update', $id), [
                'name' => 'Zegar',
                'difficulty' => 'medium',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'clock_sdt', 'duration_seconds' => 300],
                ],
                'remove_image' => '1',
            ])
            ->assertRedirect(route('admin.training.show', $id));

        $this->assertDatabaseHas('training_definitions', [
            'id' => $id,
            'image_path' => null,
        ]);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($admin)
            ->from(route('admin.training.edit', $id))
            ->put(route('admin.training.update', $id), [
                'name' => 'Zegar',
                'difficulty' => 'medium',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'clock_sdt'],
                ],
                'image' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ])
            ->assertRedirect(route('admin.training.edit', $id))
            ->assertSessionHasErrors('image');
    }

    public function test_admin_removes_an_exercise_from_an_unpublished_plan(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.training.store', 'plans'), [
                'name' => 'Mieszany',
                'difficulty' => 'easy',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'break', 'break_seconds' => 45],
                    ['kind' => 'exercise', 'exercise_key' => 'loose_throws'],
                    ['kind' => 'exercise', 'exercise_key' => 'nine_darts'],
                ],
            ])
            ->assertRedirect();

        $planId = $this->idOf('Mieszany');

        $this->actingAs($admin)
            ->put(route('admin.training.update', $planId), [
                'name' => 'Mieszany',
                'difficulty' => 'easy',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'break', 'break_seconds' => 45],
                    ['kind' => 'exercise', 'exercise_key' => 'nine_darts', 'dart_count' => 72],
                ],
            ])
            ->assertRedirect(route('admin.training.show', $planId));

        $this->assertDatabaseMissing('training_definition_items', [
            'definition_id' => $planId,
            'exercise_key' => 'loose_throws',
        ]);
        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $planId,
            'exercise_key' => 'nine_darts',
            'dart_count' => 72,
        ]);
        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $planId,
            'item_kind' => 'break',
            'break_seconds' => 45,
        ]);
    }

    public function test_cancelling_a_plan_requires_password_and_hides_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.training.store', 'plans'), [
                'name' => 'Wieczór',
                'difficulty' => 'medium',
                'declared_minutes' => 60,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'loose_throws'],
                ],
            ])
            ->assertRedirect();

        $planId = $this->idOf('Wieczór');

        $this->actingAs($admin)
            ->from(route('admin.training.show', $planId))
            ->post(route('admin.training.cancel', $planId), [
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('acknowledge');

        $this->actingAs($admin)
            ->from(route('admin.training.show', $planId))
            ->post(route('admin.training.cancel', $planId), [
                'current_password' => 'wrong-password',
                'acknowledge' => '1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('training_definitions', ['id' => $planId, 'status' => 'inactive']);

        $this->actingAs($admin)
            ->post(route('admin.training.cancel', $planId), [
                'current_password' => 'password',
                'acknowledge' => '1',
            ])
            ->assertRedirect(route('admin.training.list', 'plans'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('training_definitions', ['id' => $planId, 'status' => 'cancelled']);

        $this->actingAs($admin)
            ->get(route('admin.training.list', 'plans'))
            ->assertOk()
            ->assertDontSee('Wieczór');
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function idOf(string $name): int
    {
        return (int) TrainingDefinition::query()->where('name', $name)->value('id');
    }
}
