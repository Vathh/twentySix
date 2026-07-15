<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create(['email' => 'user1@test.com']);
        app(PlayerService::class)->create('Tomek', $this->user1->id);
    }

    public function test_quick_game_update_requires_game_id(): void
    {
        Sanctum::actingAs($this->user1);

        $this->postJson('/api/quick-game/update', [
            'achievements' => [],
        ])->assertStatus(422);
    }

    public function test_legacy_create_active_in_progress_routes_are_gone(): void
    {
        Sanctum::actingAs($this->user1);

        $this->postJson('/api/quick-game/create', [
            'player1Id' => 1,
            'player2Id' => 2,
        ])->assertNotFound();

        $this->getJson('/api/quick-game/active')->assertNotFound();

        $this->postJson('/api/quick-game/inProgress', [
            'gameId' => 1,
        ])->assertNotFound();
    }

    public function test_quick_game_update_accepts_achievements_after_ffa_finish(): void
    {
        Sanctum::actingAs($this->user1);

        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');
        $friend = User::factory()->create(['email' => 'ach-friend@test.com']);
        app(PlayerService::class)->create('AchFriend', $friend->id);
        $friendPlayer = Player::where('user_id', $friend->id)->first();
        $this->postJson('/api/friends/add', ['friendId' => $friend->id])->assertCreated();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $friendPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($this->user1);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 1, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => 'x01',
            'scoringMode' => 'each_own',
        ])->assertOk();

        $hostPlayer = Player::where('user_id', $this->user1->id)->first();
        $visits = [
            ['playerId' => $hostPlayer->id, 'score' => 180, 'remainingBefore' => 501, 'remainingAfter' => 321],
            ['playerId' => $friendPlayer->id, 'score' => 180, 'remainingBefore' => 501, 'remainingAfter' => 321],
            ['playerId' => $hostPlayer->id, 'score' => 180, 'remainingBefore' => 321, 'remainingAfter' => 141],
            ['playerId' => $friendPlayer->id, 'score' => 180, 'remainingBefore' => 321, 'remainingAfter' => 141],
            ['playerId' => $hostPlayer->id, 'score' => 141, 'remainingBefore' => 141, 'remainingAfter' => 0, 'closedLeg' => true],
        ];
        foreach ($visits as $v) {
            Sanctum::actingAs(
                (int) $v['playerId'] === (int) $hostPlayer->id ? $this->user1 : $friend
            );
            $payload = array_merge([
                'dartsInVisit' => 3,
                'closedLeg' => false,
                'bust' => false,
                'clientVisitId' => (string) Str::uuid(),
            ], $v);
            $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", $payload)->assertOk();
        }

        $session = \App\Models\QuickGame\QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertNotNull($session->quick_game_id);

        $this->postJson('/api/quick-game/update', [
            'gameId' => $session->quick_game_id,
            'achievements' => [
                [
                    'playerId' => $hostPlayer->id,
                    'type' => 'max',
                    'value' => null,
                ],
            ],
        ])->assertOk()->assertJson(['success' => true]);
    }
}
