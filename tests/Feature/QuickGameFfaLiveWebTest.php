<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameFfaLiveWebTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'ffa-live-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'ffa-live-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_live_page_returns_200_when_session_in_progress(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        $this->get(route('quick-game.ffa.live', ['lobbyId' => $lobbyId]))
            ->assertOk()
            ->assertSee('Szybki mecz FFA');
    }

    public function test_live_state_returns_scoring_state_json_when_session_in_progress(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        $this->getJson(route('quick-game.ffa.live.state', ['lobbyId' => $lobbyId]))
            ->assertOk()
            ->assertJsonPath('session.lobbyId', $lobbyId)
            ->assertJsonCount(2, 'players');
    }

    public function test_live_page_redirects_to_game_show_after_session_finished(): void
    {
        $lobbyId = $this->finishTwoPlayerLobby();

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertNotNull($session->quick_game_id);

        $this->get(route('quick-game.ffa.live', ['lobbyId' => $lobbyId]))
            ->assertRedirect(route('games.show', ['type' => 'quick', 'id' => $session->quick_game_id]));
    }

    public function test_live_state_returns_410_after_session_finished(): void
    {
        $lobbyId = $this->finishTwoPlayerLobby();

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();

        $this->getJson(route('quick-game.ffa.live.state', ['lobbyId' => $lobbyId]))
            ->assertStatus(410)
            ->assertJsonPath('showUrl', route('games.show', ['type' => 'quick', 'id' => $session->quick_game_id]));
    }

    public function test_live_page_returns_404_for_unknown_lobby(): void
    {
        $this->get(route('quick-game.ffa.live', ['lobbyId' => 999999]))
            ->assertNotFound();
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

        $start = $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ]);

        $start->assertOk()->assertJsonPath('status', 'started');
        $this->assertNotNull($start->json('ffaSessionId'));

        return $lobbyId;
    }

    private function finishTwoPlayerLobby(): int
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
            'matchFormat' => ['legsToWinSet' => 1, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ])->assertOk();

        $visits = [
            ['playerId' => $this->hostPlayer->id, 'score' => 180, 'remainingBefore' => 501, 'remainingAfter' => 321],
            ['playerId' => $this->friendPlayer->id, 'score' => 180, 'remainingBefore' => 501, 'remainingAfter' => 321],
            ['playerId' => $this->hostPlayer->id, 'score' => 180, 'remainingBefore' => 321, 'remainingAfter' => 141],
            ['playerId' => $this->friendPlayer->id, 'score' => 180, 'remainingBefore' => 321, 'remainingAfter' => 141],
            ['playerId' => $this->hostPlayer->id, 'score' => 141, 'remainingBefore' => 141, 'remainingAfter' => 0, 'closedLeg' => true],
        ];

        foreach ($visits as $v) {
            Sanctum::actingAs(
                (int) $v['playerId'] === (int) $this->hostPlayer->id ? $this->host : $this->friend
            );
            $payload = array_merge([
                'dartsInVisit' => 3,
                'closedLeg' => false,
                'bust' => false,
                'clientVisitId' => (string) Str::uuid(),
            ], $v);
            $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", $payload)->assertOk();
        }

        return $lobbyId;
    }
}
