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

class QuickGameFfaAtcApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'atc-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'atc-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_atc_start_and_visit_sync(): void
    {
        $lobbyId = $this->startAtcLobby('each_own');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertSame('atc', $session->game_type);
        $this->assertIsArray($session->atc_state);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('format', 'ffa_atc')
            ->assertJsonPath('session.gameType', 'atc')
            ->assertJsonPath('session.currentTargetLabel', '1')
            ->assertJsonPath('players.0.targetIndex', 0)
            ->assertJsonPath('players.0.targetLabel', '1');

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/atc/visits", [
            'playerId' => $this->hostPlayer->id,
            'hits' => 2,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('players.0.targetIndex', 2)
            ->assertJsonPath('players.0.targetLabel', '3')
            ->assertJsonPath('session.currentPlayerIndex', 1)
            ->assertJsonPath('session.currentTargetLabel', '1');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/atc/visits/undo")
            ->assertOk()
            ->assertJsonPath('players.0.targetIndex', 0)
            ->assertJsonPath('players.0.targetLabel', '1')
            ->assertJsonPath('session.currentPlayerIndex', 0);
    }

    public function test_atc_zero_hits_stay_and_rotate(): void
    {
        $lobbyId = $this->startAtcLobby('each_own');

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/atc/visits", [
            'playerId' => $this->hostPlayer->id,
            'hits' => 0,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('players.0.targetIndex', 0)
            ->assertJsonPath('session.currentPlayerIndex', 1);
    }

    public function test_atc_x01_visits_rejected(): void
    {
        $lobbyId = $this->startAtcLobby('one_device');

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

    public function test_atc_finishing_bull_wins_match(): void
    {
        $lobbyId = $this->startAtcLobby('one_device', legsToWin: 1);

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
        $session->atc_state = [
            'boards' => [
                (string) $this->hostPlayer->id => ['targetIndex' => 20, 'finished' => false],
                (string) $this->friendPlayer->id => ['targetIndex' => 8, 'finished' => false],
            ],
            'dartLog' => [],
        ];
        $session->current_player_index = 0;
        $session->save();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/atc/visits", [
            'playerId' => $this->hostPlayer->id,
            'hits' => 1,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('game.status', 'finished')
            ->assertJsonPath('session.status', 'finished');

        $session->refresh();
        $this->assertNotNull($session->quick_game_id);
        $this->assertDatabaseHas('quick_games', [
            'id' => $session->quick_game_id,
            'game_type' => 'atc',
        ]);
        $this->assertDatabaseHas('quick_game_results', [
            'quick_game_id' => $session->quick_game_id,
            'player_id' => $this->hostPlayer->id,
            'place' => 1,
        ]);
        $this->assertNotNull(QuickGame::find($session->quick_game_id));
    }

    public function test_atc_three_from_nineteen_finishes(): void
    {
        $lobbyId = $this->startAtcLobby('one_device', legsToWin: 1);

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
        $session->atc_state = [
            'boards' => [
                (string) $this->hostPlayer->id => ['targetIndex' => 18, 'finished' => false],
                (string) $this->friendPlayer->id => ['targetIndex' => 0, 'finished' => false],
            ],
            'dartLog' => [],
        ];
        $session->current_player_index = 0;
        $session->save();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/atc/visits", [
            'playerId' => $this->hostPlayer->id,
            'hits' => 3,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('game.status', 'finished');
    }

    private function startAtcLobby(string $scoringMode, int $legsToWin = 2): int
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
                'gameType' => 'atc',
            ],
            'gameType' => 'atc',
            'scoringMode' => $scoringMode,
        ])->assertOk();

        return $lobbyId;
    }
}
