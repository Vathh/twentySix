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

class QuickGameFfaBob27ApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'bob27-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'bob27-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_bob27_start_and_dart_sync(): void
    {
        $lobbyId = $this->startBob27Lobby('each_own');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertSame('bob27', $session->game_type);
        $this->assertIsArray($session->bob27_state);
        $this->assertSame('hard', $session->bob27_state['mode']);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('format', 'ffa_bob27')
            ->assertJsonPath('session.gameType', 'bob27')
            ->assertJsonPath('session.currentTargetLabel', 'D1')
            ->assertJsonPath('players.0.score', 27);

        Sanctum::actingAs($this->host);
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/bob27/darts", [
            'playerId' => $this->hostPlayer->id,
            'kind' => 'hit',
            'clientDartId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('turn.hitsInVisit', 1)
            ->assertJsonPath('turn.dartsInVisit', 1);

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/bob27/darts/undo")
            ->assertOk()
            ->assertJsonPath('turn.hitsInVisit', 0)
            ->assertJsonPath('turn.dartsInVisit', 0)
            ->assertJsonPath('players.0.score', 27);
    }

    public function test_bob27_three_misses_subtract_and_rotate(): void
    {
        $lobbyId = $this->startBob27Lobby('each_own');

        for ($i = 0; $i < 3; $i++) {
            Sanctum::actingAs($this->host);
            $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/bob27/darts", [
                'playerId' => $this->hostPlayer->id,
                'kind' => 'miss',
                'clientDartId' => (string) Str::uuid(),
            ])->assertOk();
        }

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('players.0.score', 25)
            ->assertJsonPath('session.currentPlayerIndex', 1)
            ->assertJsonPath('turn.dartsInVisit', 0);
    }

    public function test_bob27_x01_visits_rejected(): void
    {
        $lobbyId = $this->startBob27Lobby('one_device');

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

    public function test_bob27_hard_last_survivor_finishes_match(): void
    {
        $lobbyId = $this->startBob27Lobby('one_device', legsToWin: 1, mode: 'hard');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->firstOrFail();
        $session->bob27_state = [
            'mode' => 'hard',
            'currentTargetIndex' => 5,
            'dartsInVisit' => 2,
            'hitsInVisit' => 0,
            'thrownThisTarget' => [],
            'boards' => [
                (string) $this->hostPlayer->id => ['score' => 2, 'eliminated' => false],
                (string) $this->friendPlayer->id => ['score' => 40, 'eliminated' => false],
            ],
            'dartLog' => [],
        ];
        $session->current_player_index = 0;
        $session->save();

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/bob27/darts", [
            'playerId' => $this->hostPlayer->id,
            'kind' => 'miss',
            'clientDartId' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('game.status', 'finished')
            ->assertJsonPath('session.status', 'finished');

        $session->refresh();
        $this->assertNotNull($session->quick_game_id);
        $this->assertDatabaseHas('quick_games', [
            'id' => $session->quick_game_id,
            'game_type' => 'bob27',
        ]);
        $this->assertDatabaseHas('quick_game_results', [
            'quick_game_id' => $session->quick_game_id,
            'player_id' => $this->friendPlayer->id,
            'place' => 1,
        ]);
        $this->assertNotNull(QuickGame::find($session->quick_game_id));
    }

    public function test_bob27_easy_allows_negative_score(): void
    {
        $lobbyId = $this->startBob27Lobby('one_device', mode: 'easy');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/bob27/darts", [
                'playerId' => $this->hostPlayer->id,
                'kind' => 'miss',
                'clientDartId' => (string) Str::uuid(),
            ])->assertOk();
        }

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('session.bob27Mode', 'easy')
            ->assertJsonPath('players.0.score', 25)
            ->assertJsonPath('players.0.eliminated', false)
            ->assertJsonPath('game.status', 'in_progress');
    }

    private function startBob27Lobby(string $scoringMode, int $legsToWin = 2, string $mode = 'hard'): int
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
                'gameType' => 'bob27',
                'bob27Mode' => $mode,
            ],
            'gameType' => 'bob27',
            'scoringMode' => $scoringMode,
        ])->assertOk();

        return $lobbyId;
    }
}
