<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaPresence;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameFfaPresenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'presence-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'presence-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_ffa_state_includes_presence_after_start(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonCount(2, 'presence')
            ->assertJsonPath('presence.0.status', QuickGameFfaPresence::STATUS_CONNECTED)
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_CONNECTED);
    }

    public function test_presence_disconnected_updates_state(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_DISCONNECTED,
        ])->assertOk()
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_DISCONNECTED);

        Sanctum::actingAs($this->host);
        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_DISCONNECTED);
    }

    public function test_presence_left_forfeits_two_player_each_own_match(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_LEFT,
        ]);

        $response->assertOk()
            ->assertJsonPath('game.status', 'finished')
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_LEFT);

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertSame(QuickGameFfaSession::STATUS_FINISHED, $session->status);
        $this->assertNotNull($session->quick_game_id);

        $this->assertDatabaseHas('quick_game_lobbies', [
            'id' => $lobbyId,
            'status' => 'finished',
        ]);

        $quickGame = QuickGame::find($session->quick_game_id);
        $this->assertSame((int) $this->hostPlayer->id, (int) $quickGame->winner_id);

        Sanctum::actingAs($this->friend);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match', null);

        Sanctum::actingAs($this->host);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match', null);
    }

    public function test_active_match_returns_in_progress_lobby(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        Sanctum::actingAs($this->friend);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match.lobbyId', $lobbyId)
            ->assertJsonPath('match.scoringMode', 'each_own')
            ->assertJsonCount(2, 'match.players');
    }

    public function test_rejoin_after_disconnect_restores_connected(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_DISCONNECTED,
        ])->assertOk();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_CONNECTED,
        ])->assertOk()
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_CONNECTED);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_CONNECTED);
    }

    public function test_cannot_return_after_left(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_LEFT,
        ])->assertOk();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_CONNECTED,
        ])->assertStatus(422);
    }

    public function test_three_player_leave_continues_without_leaver(): void
    {
        $third = User::factory()->create(['email' => 'ffa-third@test.com']);
        app(PlayerService::class)->create('Third', $third->id);
        $thirdPlayer = Player::where('user_id', $third->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $third->id])->assertCreated();

        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->friendPlayer->id,
        ])->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $thirdPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($third);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ])->assertOk();

        // Friend (index 1) leaves — host (0) and third (2) continue
        Sanctum::actingAs($this->friend);
        $leave = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_LEFT,
        ]);
        $leave->assertOk()
            ->assertJsonPath('game.status', 'in_progress')
            ->assertJsonPath('presence.1.status', QuickGameFfaPresence::STATUS_LEFT);

        Sanctum::actingAs($this->friend);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match', null);

        Sanctum::actingAs($this->host);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match.lobbyId', $lobbyId);

        Sanctum::actingAs($third);
        $this->getJson('/api/quick-game/lobby/active-match')
            ->assertOk()
            ->assertJsonPath('match.lobbyId', $lobbyId);

        Sanctum::actingAs($this->host);
        $state = $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state");
        $state->assertOk()
            ->assertJsonPath('game.status', 'in_progress')
            ->assertJsonPath('session.currentPlayerIndex', 0);

        $visit = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ]);
        $visit->assertOk()
            ->assertJsonPath('session.currentPlayerIndex', 2);

        Sanctum::actingAs($third);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $thirdPlayer->id,
            'score' => 45,
            'remainingBefore' => 501,
            'remainingAfter' => 456,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('session.currentPlayerIndex', 0);
    }

    public function test_three_player_two_leave_forfeits_to_last(): void
    {
        $third = User::factory()->create(['email' => 'ffa-third2@test.com']);
        app(PlayerService::class)->create('Third2', $third->id);
        $thirdPlayer = Player::where('user_id', $third->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $third->id])->assertCreated();

        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->friendPlayer->id,
        ])->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $thirdPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($third);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_LEFT,
        ])->assertOk();

        Sanctum::actingAs($third);
        $finish = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/presence", [
            'status' => QuickGameFfaPresence::STATUS_LEFT,
        ]);

        $finish->assertOk()
            ->assertJsonPath('game.status', 'finished');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $quickGame = QuickGame::find($session->quick_game_id);
        $this->assertSame((int) $this->hostPlayer->id, (int) $quickGame->winner_id);
    }

    private function startTwoPlayerLobby(): int
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->friendPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ])->assertOk();

        return $lobbyId;
    }
}
