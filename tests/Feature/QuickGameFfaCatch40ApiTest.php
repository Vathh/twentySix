<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\QuickGame\QuickGameFfaSession;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameFfaCatch40ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'catch40-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'catch40-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_catch40_start_and_visit_sync(): void
    {
        $lobbyId = $this->startCatch40Lobby('each_own');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertSame('catch40', $session->game_type);
        $this->assertIsArray($session->catch40_state);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('format', 'ffa_catch40')
            ->assertJsonPath('session.gameType', 'catch40')
            ->assertJsonPath('session.currentOutNumber', 61)
            ->assertJsonPath('players.0.remaining', 61)
            ->assertJsonPath('players.0.catch40Score', 0);

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/catch40/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 20,
            'remainingBefore' => 61,
            'remainingAfter' => 41,
            'dartsInVisit' => 3,
            'bust' => false,
            'checkout' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('players.0.remaining', 41)
            ->assertJsonPath('players.0.dartsUsed', 3)
            ->assertJsonPath('session.currentPlayerIndex', 1);

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/catch40/visits/undo")
            ->assertOk()
            ->assertJsonPath('players.0.remaining', 61)
            ->assertJsonPath('players.0.dartsUsed', 0)
            ->assertJsonPath('session.currentPlayerIndex', 0);
    }

    public function test_catch40_checkout_advances_own_out(): void
    {
        $lobbyId = $this->startCatch40Lobby('one_device');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/catch40/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 61,
            'remainingBefore' => 61,
            'remainingAfter' => 0,
            'dartsInVisit' => 2,
            'bust' => false,
            'checkout' => true,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('players.0.outNumber', 62)
            ->assertJsonPath('players.0.catch40Score', 3)
            ->assertJsonPath('players.1.outNumber', 61)
            ->assertJsonPath('session.currentPlayerIndex', 1);
    }

    public function test_catch40_x01_visits_rejected(): void
    {
        $lobbyId = $this->startCatch40Lobby('one_device');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_catch40_all_outs_done_highest_wins_match(): void
    {
        $lobbyId = $this->startCatch40Lobby('one_device', legsToWin: 1);

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
        $session->catch40_state = [
            'boards' => [
                (string) $this->hostPlayer->id => [
                    'outNumber' => 100,
                    'remaining' => 40,
                    'dartsUsed' => 0,
                    'catch40Score' => 50,
                    'finished' => false,
                ],
                (string) $this->friendPlayer->id => [
                    'outNumber' => 100,
                    'remaining' => 0,
                    'dartsUsed' => 0,
                    'catch40Score' => 40,
                    'finished' => true,
                ],
            ],
            'dartLog' => [],
        ];
        $session->current_player_index = 0;
        $session->save();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/catch40/visits", [
            'playerId' => $this->hostPlayer->id,
            'score' => 40,
            'remainingBefore' => 40,
            'remainingAfter' => 0,
            'dartsInVisit' => 2,
            'bust' => false,
            'checkout' => true,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('game.status', 'finished')
            ->assertJsonPath('session.status', 'finished');

        $session->refresh();
        $this->assertNotNull($session->quick_game_id);
        $this->assertDatabaseHas('quick_games', [
            'id' => $session->quick_game_id,
            'game_type' => 'catch40',
        ]);
        $this->assertDatabaseHas('quick_game_results', [
            'quick_game_id' => $session->quick_game_id,
            'player_id' => $this->hostPlayer->id,
            'place' => 1,
        ]);
        $this->assertNotNull(QuickGame::find($session->quick_game_id));
    }

    private function startCatch40Lobby(string $scoringMode, int $legsToWin = 2): int
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
            'matchFormat' => [
                'legsToWinSet' => $legsToWin,
                'setsToWinMatch' => 1,
                'startingScore' => 501,
                'gameType' => 'catch40',
            ],
            'gameType' => 'catch40',
            'scoringMode' => $scoringMode,
        ])->assertOk();

        return $lobbyId;
    }
}
