<?php

namespace Tests\Feature;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Models\Users\User;
use App\Services\Training\TrainingDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TrainingFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_belong_to_the_account_and_keep_tab_order(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $other = User::factory()->create();
        $challenge = (int) \App\Models\Training\TrainingDefinition::query()
            ->where('kind', 'challenge')
            ->where('mechanic', 'fifty_four_dart')
            ->where('status', 'active')
            ->value('id');
        $first = $this->publish($admin, [
            'kind' => 'plan',
            'name' => 'Pierwszy',
            'difficulty' => 'easy',
            'declaredMinutes' => 30,
            'items' => [
                ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
            ],
        ]);
        $second = $this->publish($admin, [
            'kind' => 'plan',
            'name' => 'Drugi',
            'difficulty' => 'medium',
            'declaredMinutes' => 60,
            'items' => [
                ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
            ],
        ]);

        $this->asGuest();
        $this->getJson('/api/training/favorites')->assertUnauthorized();
        $this->postJson('/api/training/favorites', ['definitionId' => $first])->assertUnauthorized();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $challenge])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('item.name', '54-Dart Challenge')
            ->assertJsonPath('item.challengeKey', 'fifty_four_dart')
            ->assertJsonPath('item.favorite', true);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $first])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('item.name', 'Pierwszy')
            ->assertJsonPath('item.favorite', true);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $first])
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $second])
            ->assertCreated();

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonPath('challenges', [])
            ->assertJsonPath('plans', [])
            ->assertJsonPath('exercises', []);

        $this->actingAs($player, 'sanctum')
            ->putJson('/api/training/favorites', [
                'kind' => 'plans',
                'definitionIds' => [$second, $first],
            ])
            ->assertOk()
            ->assertJsonPath('items.0.id', $second)
            ->assertJsonPath('items.1.id', $first);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonPath('challenges.0.id', $challenge)
            ->assertJsonPath('plans.0.id', $second)
            ->assertJsonPath('plans.1.id', $first);

        $this->actingAs($player, 'sanctum')
            ->putJson('/api/training/favorites', [
                'kind' => 'plans',
                'definitionIds' => [$first],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_favorite_order');

        $this->actingAs($player, 'sanctum')
            ->putJson('/api/training/favorites', [
                'kind' => 'plans',
                'definitionIds' => [],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('training_favorites', 3);
    }

    public function test_inactive_plan_stays_visible_only_to_who_saved_it(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->publish($admin, [
            'kind' => 'plan',
            'name' => 'Wieczór',
            'difficulty' => 'medium',
            'declaredMinutes' => 60,
            'items' => [
                ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
            ],
        ]);
        $event = $this->publish($admin, [
            'kind' => 'event',
            'name' => 'Sierpień',
            'startsAt' => now()->subHour()->toIso8601String(),
            'endsAt' => now()->addDays(7)->toIso8601String(),
            'attemptsPerWindow' => 1,
            'windowMode' => 'calendar_week',
            'attemptTimeoutHours' => 4,
            'items' => [
                ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
            ],
        ]);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['exerciseKey' => 'loose_throws'])
            ->assertCreated()
            ->assertJsonPath('item.name', 'Luźne rzuty')
            ->assertJsonPath('item.exerciseKey', 'loose_throws');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $plan])
            ->assertCreated();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $event])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_favorite_not_allowed');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['exerciseKey' => 'not_an_exercise'])
            ->assertNotFound()
            ->assertJsonPath('reason', 'training_exercise_not_found');

        app(TrainingDefinitionService::class)->deactivate($admin, $plan);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonPath('exercises.0.id', 'loose_throws')
            ->assertJsonPath('exercises.0.inactive', false)
            ->assertJsonPath('plans.0.id', $plan)
            ->assertJsonPath('plans.0.inactive', true);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/catalog/plans')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/definitions/'.$plan)
            ->assertOk()
            ->assertJsonPath('inactive', true)
            ->assertJsonPath('favorite', true);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/training/definitions/'.$plan)
            ->assertNotFound()
            ->assertJsonPath('reason', 'training_definition_not_found');

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/favorites/'.$plan)
            ->assertOk()
            ->assertJsonPath('removed', true);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/definitions/'.$plan)
            ->assertNotFound();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => $plan])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_favorite_inactive');
    }

    public function test_cancel_drops_plan_favorites_and_leaves_exercises(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->publish($admin, [
            'kind' => 'plan',
            'name' => 'Wieczór',
            'difficulty' => 'medium',
            'declaredMinutes' => 30,
            'items' => [
                ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
                ['kind' => 'exercise', 'exerciseKey' => 'clock_sdt'],
            ],
        ]);

        foreach ([$player, $other] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/training/favorites', ['exerciseKey' => 'loose_throws'])
                ->assertCreated();
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/training/favorites', ['definitionId' => $plan])
                ->assertCreated();
        }

        app(TrainingDefinitionService::class)->cancel($admin, $plan);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonPath('plans', [])
            ->assertJsonPath('exercises.0.id', 'loose_throws');
        $this->actingAs($other, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonPath('plans', [])
            ->assertJsonPath('exercises.0.id', 'loose_throws');
        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/definitions/'.$plan)
            ->assertNotFound();

        $this->assertDatabaseMissing('training_favorites', ['definition_id' => $plan]);
        $this->assertDatabaseHas('training_favorites', ['exercise_key' => 'loose_throws']);
    }

    public function test_catalog_lists_every_exercise_with_the_star_state(): void
    {
        $player = User::factory()->create();

        $this->asGuest();
        $this->getJson('/api/training/catalog/exercises')->assertUnauthorized();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/catalog/exercises')
            ->assertOk()
            ->assertJsonCount(count(TrainingExerciseCatalog::all()), 'items')
            ->assertJsonPath('items.0.id', 'loose_throws')
            ->assertJsonPath('items.0.name', 'Luźne rzuty')
            ->assertJsonPath('items.0.durationSeconds', 180)
            ->assertJsonPath('items.0.favorite', false)
            ->assertJsonPath('items.0.inactive', false);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['exerciseKey' => 'loose_throws'])
            ->assertCreated();
        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['exerciseKey' => 'nine_darts', 'variant' => '9'])
            ->assertCreated()
            ->assertJsonPath('item.variant', '9')
            ->assertJsonPath('item.name', '9/18 lotek — 9 lotek');

        $this->actingAs($player, 'sanctum')
            ->putJson('/api/training/favorites', [
                'kind' => 'exercises',
                'items' => [
                    ['exerciseKey' => 'nine_darts', 'variant' => '9'],
                    ['exerciseKey' => 'loose_throws'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('items.0.id', 'nine_darts')
            ->assertJsonPath('items.0.variant', '9')
            ->assertJsonPath('items.1.id', 'loose_throws');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/catalog/exercises')
            ->assertOk()
            ->assertJsonPath('items.0.favorite', true);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/exercises/loose_throws')
            ->assertOk()
            ->assertJsonPath('name', 'Luźne rzuty')
            ->assertJsonPath('favorite', true);

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/favorites/exercises/loose_throws')
            ->assertOk()
            ->assertJsonPath('removed', true);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['definitionId' => 99999])
            ->assertNotFound();
    }

    public function test_exercise_favorites_are_per_variant(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', ['exerciseKey' => 'nine_darts'])
            ->assertStatus(422);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', [
                'exerciseKey' => 'nine_darts',
                'variant' => '9',
            ])
            ->assertCreated()
            ->assertJsonPath('item.variant', '9');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', [
                'exerciseKey' => 'nine_darts',
                'variant' => '18',
            ])
            ->assertCreated()
            ->assertJsonPath('item.variant', '18')
            ->assertJsonPath('item.name', '9/18 lotek — 18 lotek');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/favorites', [
                'exerciseKey' => 'clock_1_20_bull',
            ])
            ->assertCreated()
            ->assertJsonPath('item.name', 'Zegar — Szybki');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonCount(3, 'exercises');

        $this->actingAs($player, 'sanctum')
            ->deleteJson('/api/training/favorites/exercises/nine_darts?variant=9')
            ->assertOk()
            ->assertJsonPath('removed', true);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/favorites')
            ->assertOk()
            ->assertJsonCount(2, 'exercises')
            ->assertJsonPath('exercises.0.variant', '18');
    }

    private function asGuest(): void
    {
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publish(User $admin, array $payload): int
    {
        $id = (int) $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', $payload)
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertOk();

        return $id;
    }
}
