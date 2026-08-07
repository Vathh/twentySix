<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameLobby;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameLobbyRematchTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    private int $finishedLobbyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'rematch-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'rematch-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();

        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->friendPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/join")->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ready")->assertOk();

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => 'x01',
            'scoringMode' => 'each_own',
        ])->assertOk();

        QuickGameLobby::where('id', $lobbyId)->update(['status' => 'finished']);
        $this->finishedLobbyId = $lobbyId;
    }

    public function test_non_host_intent_waits_for_host(): void
    {
        Sanctum::actingAs($this->friend);

        $response = $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch/intent");

        $response->assertOk()
            ->assertJsonPath('status', 'waiting_for_host')
            ->assertJsonPath('waitingForHost', true)
            ->assertJsonPath('intents.0.playerId', $this->friendPlayer->id)
            ->assertJsonPath('lobby', null);
    }

    public function test_host_creates_rematch_and_auto_adds_intent_players(): void
    {
        Event::fake([\App\Events\QuickGameRematchCreated::class]);

        Sanctum::actingAs($this->friend);
        $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch/intent")->assertOk();

        Sanctum::actingAs($this->host);
        $response = $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch");

        $response->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('lobby.status', 'waiting')
            ->assertJsonPath('lobby.scoringMode', 'each_own')
            ->assertJsonPath('lobby.matchFormat.legsToWinSet', 2)
            ->assertJsonCount(2, 'lobby.players');

        $playerIds = collect($response->json('lobby.players'))->pluck('playerId')->all();
        $this->assertContains($this->hostPlayer->id, $playerIds);
        $this->assertContains($this->friendPlayer->id, $playerIds);

        $friendRow = collect($response->json('lobby.players'))
            ->firstWhere('playerId', $this->friendPlayer->id);
        $this->assertTrue($friendRow['ready']);

        Event::assertDispatched(\App\Events\QuickGameRematchCreated::class);
    }

    public function test_host_rematch_recreates_guests(): void
    {
        Sanctum::actingAs($this->host);
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/add-guest", [
            'tempPlayerName' => 'Gość',
        ])->assertOk();
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 301],
            'gameType' => 'x01',
            'scoringMode' => 'one_device',
        ])->assertOk();
        QuickGameLobby::where('id', $lobbyId)->update(['status' => 'finished']);

        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/rematch");

        $response->assertOk()
            ->assertJsonPath('lobby.scoringMode', 'one_device')
            ->assertJsonPath('lobby.matchFormat.startingScore', 301);

        $names = collect($response->json('lobby.players'))->pluck('name')->all();
        $this->assertContains('Gość', $names);
    }

    public function test_late_intent_joins_existing_rematch_without_invite(): void
    {
        Sanctum::actingAs($this->host);
        $created = $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch");
        $created->assertOk();
        $rematchId = $created->json('lobby.id');
        $this->assertCount(1, $created->json('lobby.players'));

        Sanctum::actingAs($this->friend);
        $late = $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch/intent");

        $late->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('lobby.id', $rematchId)
            ->assertJsonCount(2, 'lobby.players');
    }

    public function test_non_host_cannot_create_rematch(): void
    {
        Sanctum::actingAs($this->friend);
        $response = $this->postJson("/api/quick-game/lobby/{$this->finishedLobbyId}/rematch");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Tylko host może utworzyć rematch');
    }

    public function test_rematch_rejected_when_lobby_not_finished(): void
    {
        Sanctum::actingAs($this->host);
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/rematch");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Rematch jest dostępny tylko po zakończeniu meczu');
    }
}
