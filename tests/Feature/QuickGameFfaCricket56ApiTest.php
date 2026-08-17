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

class QuickGameFfaCricket56ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'cricket56-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'cricket56-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_cricket56_start_and_visit_sync(): void
    {
        $lobbyId = $this->startCricket56Lobby('each_own');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertSame('cricket56', $session->game_type);
        $this->assertIsArray($session->cricket56_state);
        $this->assertSame(0, $session->cricket56_state['currentRoundIndex']);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('format', 'ffa_cricket56')
            ->assertJsonPath('session.gameType', 'cricket56')
            ->assertJsonPath('session.currentTargetLabel', '15')
            ->assertJsonPath('players.0.score', 0);

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/cricket56/visits", [
            'playerId' => $this->hostPlayer->id,
            'points' => 9,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('players.0.score', 9)
            ->assertJsonPath('session.currentPlayerIndex', 1)
            ->assertJsonPath('session.currentRoundIndex', 0);

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/cricket56/visits/undo")
            ->assertOk()
            ->assertJsonPath('players.0.score', 0)
            ->assertJsonPath('session.currentPlayerIndex', 0);
    }

    public function test_cricket56_advances_round_after_everyone_throws(): void
    {
        $lobbyId = $this->startCricket56Lobby('one_device');

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/cricket56/visits", [
            'playerId' => $this->hostPlayer->id,
            'points' => 3,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/cricket56/visits", [
            'playerId' => $this->friendPlayer->id,
            'points' => 1,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('session.currentRoundIndex', 1)
            ->assertJsonPath('session.currentTargetLabel', '16')
            ->assertJsonPath('players.0.score', 3)
            ->assertJsonPath('players.1.score', 1);
    }

    public function test_cricket56_x01_visits_rejected(): void
    {
        $lobbyId = $this->startCricket56Lobby('one_device');

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

    public function test_cricket56_highest_score_finishes_match(): void
    {
        $lobbyId = $this->startCricket56Lobby('one_device', legsToWin: 1);

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
        $session->cricket56_state = [
            'currentRoundIndex' => 6,
            'dartsInVisit' => 0,
            'thrownThisRound' => [0 => true],
            'boards' => [
                (string) $this->hostPlayer->id => ['score' => 40],
                (string) $this->friendPlayer->id => ['score' => 20],
            ],
            'dartLog' => [],
        ];
        $session->current_player_index = 1;
        $session->save();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/cricket56/visits", [
            'playerId' => $this->friendPlayer->id,
            'points' => 2,
            'clientVisitId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('game.status', 'finished')
            ->assertJsonPath('session.status', 'finished');

        $session->refresh();
        $this->assertNotNull($session->quick_game_id);
        $this->assertDatabaseHas('quick_games', [
            'id' => $session->quick_game_id,
            'game_type' => 'cricket56',
        ]);
        $this->assertDatabaseHas('quick_game_results', [
            'quick_game_id' => $session->quick_game_id,
            'player_id' => $this->hostPlayer->id,
            'place' => 1,
        ]);
        $this->assertNotNull(QuickGame::find($session->quick_game_id));
    }

    private function startCricket56Lobby(string $scoringMode, int $legsToWin = 2): int
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
                'gameType' => 'cricket56',
            ],
            'gameType' => 'cricket56',
            'scoringMode' => $scoringMode,
        ])->assertOk();

        return $lobbyId;
    }
}
