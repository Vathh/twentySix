<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainingChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_only_better_score_and_ranking_uses_contest_places(): void
    {
        $admin = $this->admin();
        $alice = $this->playerUser('Ala');
        $bob = $this->playerUser('Bartek');
        $challengeId = $this->publishChallenge($admin);

        $aliceLow = $this->completeAttempt($alice, $challengeId, 1000);
        $aliceHigh = $this->completeAttempt($alice, $challengeId, 1500);
        $bobHigh = $this->completeAttempt($bob, $challengeId, 1500);

        $this->actingAs($alice, 'sanctum')
            ->postJson('/api/training/sessions/'.$aliceLow.'/publish')
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('place', 1);

        $this->actingAs($alice, 'sanctum')
            ->postJson('/api/training/sessions/'.$aliceHigh.'/publish')
            ->assertOk()
            ->assertJsonPath('score', 1500);

        $this->actingAs($alice, 'sanctum')
            ->postJson('/api/training/sessions/'.$aliceLow.'/publish')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'training_challenge_publish_not_better');

        $this->actingAs($bob, 'sanctum')
            ->postJson('/api/training/sessions/'.$bobHigh.'/publish')
            ->assertOk();

        $ranking = $this->actingAs($alice, 'sanctum')
            ->getJson('/api/training/challenges/'.$challengeId.'/ranking')
            ->assertOk()
            ->json();

        $this->assertSame(2, $ranking['total']);
        $this->assertSame(1, $ranking['items'][0]['place']);
        $this->assertSame(1, $ranking['items'][1]['place']);
        $this->assertSame(1500, $ranking['publishedScore']);

        $this->actingAs($alice, 'sanctum')
            ->deleteJson('/api/training/challenges/'.$challengeId.'/publication')
            ->assertOk()
            ->assertJsonPath('withdrawn', true);

        $this->actingAs($alice, 'sanctum')
            ->getJson('/api/training/challenges/'.$challengeId.'/attempts')
            ->assertOk()
            ->assertJsonPath('items.0.score', 1500)
            ->assertJsonPath('publishedScore', null);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function playerUser(string $name): User
    {
        $user = User::factory()->create();
        Player::query()->create([
            'name' => $name,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function publishChallenge(User $admin): int
    {
        unset($admin);

        return (int) \App\Models\Training\TrainingDefinition::query()
            ->where('kind', 'challenge')
            ->where('mechanic', 'fifty_four_dart')
            ->where('status', 'active')
            ->value('id');
    }

    private function completeAttempt(User $user, int $definitionId, int $score): int
    {
        return (int) $this->actingAs($user, 'sanctum')
            ->postJson('/api/training/sessions', [
                'clientSessionId' => (string) Str::uuid(),
                'definitionId' => $definitionId,
                'completedAt' => now()->toIso8601String(),
                'score' => $score,
                'stats' => [
                    'challengeKey' => 'fifty_four_dart',
                    'stages' => [
                        ['id' => 'shanghai_10_15', 'label' => 'Shanghai 10–15', 'score' => 0],
                        ['id' => 'doubles', 'label' => 'Doubles', 'score' => 0],
                        ['id' => 'shanghai_16_20', 'label' => 'Shanghai 16–20', 'score' => 0],
                    ],
                    'thrown' => 54,
                ],
            ])
            ->assertCreated()
            ->json('id');
    }
}
