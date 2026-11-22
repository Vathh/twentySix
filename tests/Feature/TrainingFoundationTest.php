<?php

namespace Tests\Feature;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Models\Users\User;
use App\Services\Training\TrainingDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exercises_are_a_fixed_catalog_and_a_plan_keeps_their_defaults(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'exercise',
                'name' => 'Luźne rzuty',
                'difficulty' => 'easy',
                'mechanic' => 'loose_throws',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_exercise_is_catalog');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/training/exercises/loose_throws')
            ->assertOk()
            ->assertJsonPath('exerciseKey', 'loose_throws')
            ->assertJsonPath('name', 'Luźne rzuty')
            ->assertJsonPath('durationSeconds', 180)
            ->assertJsonPath('dartCount', null);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Wieczór',
                'difficulty' => 'medium',
                'declaredMinutes' => 60,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
                    ['kind' => 'exercise', 'exerciseKey' => 'nine_darts'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.exerciseKey', 'loose_throws')
            ->assertJsonPath('items.0.durationSeconds', 180)
            ->assertJsonPath('items.0.dartCount', null)
            ->assertJsonPath('items.1.exerciseKey', 'nine_darts')
            ->assertJsonPath('items.1.dartCount', 36)
            ->assertJsonPath('items.1.durationSeconds', null);

        $this->assertDatabaseCount('training_definitions', 1);
    }

    public function test_player_and_guest_cannot_save_a_definition(): void
    {
        $this->postJson('/api/training/definitions', [])->assertUnauthorized();
        $this->postJson('/api/training/sessions', [])->assertUnauthorized();

        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Wieczór',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('reason', 'training_forbidden');

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/definitions/1/activate')
            ->assertForbidden();

        $this->assertDatabaseCount('training_definitions', 0);
    }

    public function test_unknown_exercise_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Coś',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'not_an_exercise'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_exercise_not_found');

        $this->assertDatabaseCount('training_definitions', 0);
    }

    public function test_plan_slot_keeps_scored_flag_and_pressure_variant(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();

        $id = (int) $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Presja',
                'difficulty' => 'medium',
                'declaredMinutes' => 60,
                'items' => [
                    [
                        'kind' => 'exercise',
                        'exerciseKey' => 'scoring_under_pressure',
                        'durationSeconds' => 300,
                        'scored' => false,
                        'variant' => '20/19/18',
                    ],
                    ['kind' => 'break', 'breakSeconds' => 45],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.scored', false)
            ->assertJsonPath('items.0.variant', '20/19/18')
            ->assertJsonPath('items.1.kind', 'break')
            ->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/definitions/'.$id)
            ->assertOk()
            ->assertJsonPath('completionCount', 0)
            ->assertJsonPath('items.0.scored', false)
            ->assertJsonPath('items.0.variant', '20/19/18')
            ->assertJsonPath('items.0.name', 'Scoring z presją')
            ->assertJsonPath('items.1.breakSeconds', 45);
    }

    public function test_plan_overrides_only_the_variable_of_the_exercise(): void
    {
        $admin = $this->admin();

        $id = (int) $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Bob',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'bobs_27_easy', 'durationSeconds' => 600],
                    ['kind' => 'exercise', 'exerciseKey' => 'bobs_27_easy', 'durationSeconds' => 480],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.exerciseKey', 'bobs_27_easy')
            ->assertJsonPath('items.0.durationSeconds', 600)
            ->assertJsonPath('items.1.durationSeconds', 480)
            ->assertJsonPath('items.0.dartCount', null)
            ->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Zła zmienna',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'bobs_27_easy', 'dartCount' => 20],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_exercise_wrong_limit');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Pusto',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'bobs_27_easy', 'durationSeconds' => null],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_exercise_needs_limit');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertOk();

        $this->actingAs($admin)
            ->from(route('admin.training.show', $id))
            ->put(route('admin.training.update', $id), [
                'name' => 'Bob',
                'difficulty' => 'easy',
                'declared_minutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exercise_key' => 'bobs_27_easy', 'duration_seconds' => 300],
                ],
            ])
            ->assertRedirect(route('admin.training.show', $id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $id,
            'exercise_key' => 'bobs_27_easy',
            'duration_seconds' => 600,
        ]);
        $this->assertDatabaseHas('training_definition_items', [
            'definition_id' => $id,
            'exercise_key' => 'bobs_27_easy',
            'duration_seconds' => 480,
        ]);
    }

    public function test_plan_uses_catalog_exercises_and_stays_inactive(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Nadpisany czas',
                'difficulty' => 'medium',
                'declaredMinutes' => 60,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws', 'durationSeconds' => 10],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('items.0.durationSeconds', 10);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'challenge',
                'name' => 'Z czasem',
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws', 'durationSeconds' => 10],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_exercise_limit_on_plan');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Wieczór',
                'difficulty' => 'medium',
                'declaredMinutes' => 60,
                'items' => [
                    ['kind' => 'break', 'breakSeconds' => 30],
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
                    ['kind' => 'break', 'breakSeconds' => 60],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('declaredMinutes', 60)
            ->assertJsonPath('durationSeconds', null)
            ->assertJsonPath('items.0.kind', 'break')
            ->assertJsonPath('items.0.breakSeconds', 30)
            ->assertJsonPath('items.1.kind', 'exercise')
            ->assertJsonPath('items.1.exerciseKey', 'loose_throws')
            ->assertJsonPath('items.1.position', 2)
            ->assertJsonPath('items.2.position', 3);

        $this->assertDatabaseHas('training_definitions', [
            'name' => 'Wieczór',
            'status' => 'inactive',
            'declared_minutes' => 60,
        ]);
    }

    public function test_catalog_challenge_is_ready_and_admin_cannot_create_one(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'challenge',
                'name' => '54 lotki',
                'mechanic' => 'fifty_four_dart',
                'badgeThresholds' => [
                    'iron' => 500,
                    'bronze' => 1000,
                    'gold' => 1500,
                    'platinum' => 2000,
                    'diamond' => 2500,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_challenge_is_catalog');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/challenges/fifty_four_dart')
            ->assertOk()
            ->assertJsonPath('kind', 'challenge')
            ->assertJsonPath('challengeKey', 'fifty_four_dart')
            ->assertJsonPath('name', '54-Dart Challenge')
            ->assertJsonPath('inactive', false);

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/catalog/challenges')
            ->assertOk()
            ->assertJsonPath('items.0.challengeKey', 'fifty_four_dart');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'event',
                'name' => 'Sierpień',
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'clock_1_20_bull'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'event')
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('items.0.exerciseKey', 'clock_1_20_bull')
            ->assertJsonPath('items.0.dartCount', 21);
    }

    public function test_second_completed_session_is_rejected_and_the_first_stays(): void
    {
        $player = User::factory()->create();
        $other = User::factory()->create();
        $uuid = '11111111-1111-4111-8111-111111111111';

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload($uuid, 'loose_throws', 12, [
                'hits' => 4,
                'attempts' => 9,
            ]))
            ->assertCreated()
            ->assertJsonPath('score', 12)
            ->assertJsonPath('kind', 'exercise')
            ->assertJsonPath('exerciseKey', 'loose_throws')
            ->assertJsonPath('definitionId', null)
            ->assertJsonPath('stats.hits', 4);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload($uuid, 'loose_throws', 99, [
                'hits' => 1,
                'darts' => [20, 5, 1],
            ]))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'training_session_already_stored');

        $this->assertDatabaseCount('training_sessions', 1);
        $this->assertDatabaseHas('training_sessions', [
            'user_id' => $player->id,
            'client_session_id' => $uuid,
            'exercise_key' => 'loose_throws',
            'definition_id' => null,
            'score' => 12,
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload($uuid, 'loose_throws', 3, [
                'hits' => 1,
            ]))
            ->assertCreated();

        $this->assertDatabaseCount('training_sessions', 2);
        $this->assertDatabaseHas('training_sessions', [
            'user_id' => $player->id,
            'client_session_id' => $uuid,
            'score' => 12,
        ]);
    }

    public function test_dart_list_and_unknown_exercise_do_not_create_a_session(): void
    {
        $admin = $this->admin();
        $player = User::factory()->create();
        $planId = $this->publishPlan($admin);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload(
                '22222222-2222-4222-8222-222222222222',
                'loose_throws',
                null,
                ['darts' => [20, 19, 18]],
            ))
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_dart_log_rejected');

        app(TrainingDefinitionService::class)->deactivate($admin, $planId);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => '33333333-3333-4333-8333-333333333333',
                'definitionId' => $planId,
                'completedAt' => '2026-11-19T12:00:00Z',
                'score' => 8,
                'stats' => ['elapsedSeconds' => 180],
            ])
            ->assertCreated()
            ->assertJsonPath('definitionId', $planId)
            ->assertJsonPath('score', 8);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload(
                '44444444-4444-4444-8444-444444444444',
                'not_an_exercise',
                null,
                ['elapsedSeconds' => 180],
            ))
            ->assertNotFound()
            ->assertJsonPath('reason', 'training_exercise_not_found');

        $this->assertDatabaseCount('training_sessions', 1);
    }

    public function test_history_lists_sessions_in_pages_of_five(): void
    {
        $player = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->actingAs($player, 'sanctum')
                ->postJson('/api/training/sessions', [
                    'clientSessionId' => sprintf('55555555-5555-4555-8555-55555555555%d', $i),
                    'exerciseKey' => 'loose_throws',
                    'completedAt' => sprintf('2026-11-%02dT12:00:00Z', 10 + $i),
                    'score' => $i,
                    'stats' => ['elapsedSeconds' => 180],
                ])
                ->assertCreated();
        }

        $first = $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sessions?kinds=exercise&days=90')
            ->assertOk()
            ->assertJsonPath('hasMore', true)
            ->assertJsonCount(5, 'items')
            ->assertJsonPath('items.0.name', 'Luźne rzuty')
            ->json();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sessions?kinds=exercise&days=90'
                .'&beforeCompletedAt='.urlencode($first['items'][4]['completedAt'])
                .'&beforeId='.$first['items'][4]['id'])
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'items');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/sessions/'.$first['items'][0]['id'])
            ->assertOk()
            ->assertJsonPath('stats.elapsedSeconds', 180);
    }

    public function test_records_and_dry_goals_endpoints(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload(
                '66666666-6666-4666-8666-666666666661',
                'bull_counter',
                12,
                ['hits' => 3, 'attempts' => 5],
            ))
            ->assertCreated();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload(
                '66666666-6666-4666-8666-666666666662',
                'bull_counter',
                20,
                ['hits' => 5, 'attempts' => 5],
            ))
            ->assertCreated();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/records')
            ->assertOk()
            ->assertJsonPath('items.0.exerciseKey', 'bull_counter')
            ->assertJsonPath('items.0.score', 20)
            ->assertJsonPath('items.0.name', 'Walka o licznik');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/training/goals')
            ->assertOk()
            ->assertJsonCount(5, 'goals')
            ->assertJsonPath('goals.0.value', 0)
            ->assertJsonPath('goals.0.progress', 0)
            ->assertJsonCount(4, 'badges')
            ->assertJsonPath('badges.0.placeholder', true)
            ->assertJsonPath('badges.0.level', null)
            ->assertJsonCount(1, 'medals');
    }

    public function test_clock_summary_is_stored_once_and_a_repeat_does_not_add_history(): void
    {
        $player = User::factory()->create();
        $uuid = '55555555-5555-4555-8555-555555555555';
        $stats = ['hits' => 15, 'attempts' => 21, 'percent' => 71];

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload($uuid, 'clock_1_20_bull', null, $stats))
            ->assertCreated()
            ->assertJsonPath('score', null)
            ->assertJsonPath('exerciseKey', 'clock_1_20_bull')
            ->assertJsonPath('stats.hits', 15)
            ->assertJsonPath('stats.attempts', 21)
            ->assertJsonPath('stats.percent', 71);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/training/sessions', $this->sessionPayload($uuid, 'clock_1_20_bull', null, [
                'hits' => 21,
                'attempts' => 21,
                'percent' => 100,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'training_session_already_stored');

        $this->assertDatabaseCount('training_sessions', 1);
        $this->assertDatabaseHas('training_sessions', [
            'user_id' => $player->id,
            'client_session_id' => $uuid,
            'exercise_key' => 'clock_1_20_bull',
            'score' => null,
        ]);
    }

    public function test_activation_is_only_from_inactive(): void
    {
        $admin = $this->admin();
        $id = $this->publishPlan($admin);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_definition_not_inactive');

        $this->assertDatabaseHas('training_definitions', [
            'id' => $id,
            'status' => 'active',
        ]);

        $this->assertSame(180, TrainingExerciseCatalog::parameters('loose_throws')['durationSeconds']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function publishPlan(User $admin): int
    {
        $id = (int) $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions', [
                'kind' => 'plan',
                'name' => 'Luźny wieczór',
                'difficulty' => 'easy',
                'declaredMinutes' => 30,
                'items' => [
                    ['kind' => 'exercise', 'exerciseKey' => 'loose_throws'],
                ],
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/training/definitions/'.$id.'/activate')
            ->assertOk()
            ->assertJsonPath('status', 'active');

        return $id;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function sessionPayload(string $uuid, string $exerciseKey, ?int $score, array $stats): array
    {
        return [
            'clientSessionId' => $uuid,
            'exerciseKey' => $exerciseKey,
            'completedAt' => '2026-11-19T12:00:00Z',
            'score' => $score,
            'stats' => $stats,
        ];
    }
}
