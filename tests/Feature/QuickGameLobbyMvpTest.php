<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameLobby;
use App\Models\QuickGame\QuickGameLobbyPlayer;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameLobbyMvpTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private User $stranger;

    private Player $hostPlayer;

    private Player $friendPlayer;

    private Player $strangerPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'lobby-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'lobby-friend@test.com']);
        $this->stranger = User::factory()->create(['email' => 'lobby-stranger@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);
        $playerService->create('Stranger', $this->stranger->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();
        $this->strangerPlayer = Player::where('user_id', $this->stranger->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_new_lobby_defaults_to_bo3_two_legs(): void
    {
        $response = $this->postJson('/api/quick-game/lobby/create');

        $response->assertOk()
            ->assertJsonPath('legsCount', 2);
    }

    public function test_invite_non_friend_is_rejected(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->strangerPlayer->id,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Do quick game można zapraszać tylko znajomych');
    }

    public function test_host_can_add_guest(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/add-guest", [
            'tempPlayerName' => 'Gość',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'players')
            ->assertJsonPath('players.1.name', 'Gość')
            ->assertJsonPath('players.1.isRegistered', false);

        $this->assertDatabaseHas('quick_game_lobby_players', [
            'lobby_id' => $lobbyId,
            'temp_player_name' => 'Gość',
            'is_registered' => false,
        ]);
    }

    public function test_start_with_guest_requires_one_device_mode(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/add-guest", [
            'tempPlayerName' => 'Kumpel',
        ])->assertOk();

        $start = $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'legsCount' => 2,
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ]);

        $start->assertStatus(400)
            ->assertJsonPath('message', 'Gracze tymczasowi wymagają trybu „na jednym urządzeniu”');

        $startOk = $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'legsCount' => 2,
            'gameType' => '501',
            'scoringMode' => 'one_device',
        ]);

        $startOk->assertOk()
            ->assertJsonPath('status', 'started');
    }

    public function test_lobby_rejects_ninth_player(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');
        $lobby = QuickGameLobby::findOrFail($lobbyId);

        for ($i = 0; $i < 7; $i++) {
            QuickGameLobbyPlayer::create([
                'lobby_id' => $lobbyId,
                'player_id' => null,
                'temp_player_name' => 'Fill'.$i,
                'is_registered' => false,
                'is_ready' => false,
            ]);
        }

        $this->assertSame(8, $lobby->players()->count());

        Sanctum::actingAs($this->friend);
        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/join");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'W lobby może być maksymalnie 8 graczy');
    }

    public function test_start_two_player_lobby_uses_bo3_legs_count(): void
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

        $start = $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'legsCount' => 2,
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ]);

        $start->assertOk()
            ->assertJsonPath('legsCount', 2)
            ->assertJsonPath('status', 'started');

        $ffaSessionId = $start->json('ffaSessionId');
        $this->assertNotNull($ffaSessionId);

        $session = \App\Models\QuickGame\QuickGameFfaSession::find($ffaSessionId);
        $this->assertNotNull($session);
        $this->assertSame(2, (int) $session->legs_to_win);
        $this->assertCount(2, $session->player_order);
    }

    public function test_join_without_invitation_is_rejected(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        Sanctum::actingAs($this->friend);
        $response = $this->postJson("/api/quick-game/lobby/{$lobbyId}/join");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Brak aktywnego zaproszenia do tego lobby');
    }

    public function test_friend_can_be_invited_and_join(): void
    {
        $lobbyId = $this->postJson('/api/quick-game/lobby/create')->json('id');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/invite", [
            'playerId' => $this->friendPlayer->id,
        ])->assertOk();

        Sanctum::actingAs($this->friend);
        $join = $this->postJson("/api/quick-game/lobby/{$lobbyId}/join");

        $join->assertOk()
            ->assertJsonCount(2, 'players');
    }
}
