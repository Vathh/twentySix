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

class QuickGameFfaScoringApiTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $friend;

    private Player $hostPlayer;

    private Player $friendPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['email' => 'ffa-host@test.com']);
        $this->friend = User::factory()->create(['email' => 'ffa-friend@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $this->host->id);
        $playerService->create('Friend', $this->friend->id);

        $this->hostPlayer = Player::where('user_id', $this->host->id)->first();
        $this->friendPlayer = Player::where('user_id', $this->friend->id)->first();

        Sanctum::actingAs($this->host);
        $this->postJson('/api/friends/add', ['friendId' => $this->friend->id])->assertCreated();
    }

    public function test_lobby_start_creates_ffa_session_for_two_players(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

        $this->assertDatabaseHas('quick_game_ffa_sessions', [
            'lobby_id' => $lobbyId,
            'legs_to_win_set' => 2,
            'status' => QuickGameFfaSession::STATUS_IN_PROGRESS,
        ]);

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('session.lobbyId', $lobbyId)
            ->assertJsonPath('session.legsToWinSet', 2)
            ->assertJsonCount(2, 'players');
    }

    public function test_ffa_scoring_flow_two_players(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();

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
            ->assertJsonPath('players.0.remaining', 441)
            ->assertJsonPath('session.currentPlayerIndex', 1)
            ->assertJsonPath('format', 'ffa')
            ->assertJsonPath('turn.currentPlayerIndex', 1)
            ->assertJsonPath('meta.kind', 'quick_ffa');

        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('visits.0.score', 60);

        $undo = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits/undo");
        $undo->assertOk()
            ->assertJsonCount(0, 'visits')
            ->assertJsonPath('session.currentPlayerIndex', 0);
    }

    public function test_ffa_complete_visit_retry_does_not_advance_turn_twice(): void
    {
        $lobbyId = $this->startTwoPlayerLobby();
        $clientVisitId = (string) Str::uuid();

        $payload = [
            'playerId' => $this->hostPlayer->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => $clientVisitId,
        ];

        $first = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", $payload);
        $first->assertOk()->assertJsonPath('session.currentPlayerIndex', 1);

        $second = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", $payload);
        $second->assertOk()->assertJsonPath('session.currentPlayerIndex', 1);

        $this->assertDatabaseCount('quick_game_ffa_visits', 1);
    }

    public function test_ffa_visit_rejects_spoofed_remaining_before(): void
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
        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $this->friendPlayer->id,
            'score' => 40,
            'remainingBefore' => 40,
            'remainingAfter' => 0,
            'dartsInVisit' => 1,
            'closedLeg' => true,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nieprawidłowy wynik przed wizytą.');

        $this->assertDatabaseCount('quick_game_ffa_visits', 1);
        $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")
            ->assertOk()
            ->assertJsonPath('players.0.remaining', 441)
            ->assertJsonPath('players.1.remaining', 501);
    }

    public function test_ffa_finish_creates_quick_game_result(): void
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

        $state = $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state");
        $state->assertOk()
            ->assertJsonPath('game.status', 'finished');

        $session = QuickGameFfaSession::where('lobby_id', $lobbyId)->first();
        $this->assertNotNull($session->quick_game_id);
        $this->assertSame(QuickGameFfaSession::STATUS_FINISHED, $session->status);

        $quickGame = QuickGame::find($session->quick_game_id);
        $this->assertNotNull($quickGame);
        $this->assertSame(1, (int) $quickGame->player1_score);

        $this->assertDatabaseHas('quick_game_results', [
            'quick_game_id' => $session->quick_game_id,
            'player_id' => $this->hostPlayer->id,
            'score' => 1,
            'place' => 1,
        ]);

        $this->assertDatabaseHas('player_game_snapshots', [
            'player_id' => $this->hostPlayer->id,
            'source' => 'quick',
            'sourceable_id' => $session->quick_game_id,
        ]);
    }

    private function startTwoPlayerLobby(?array $startPayload = null): int
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

        $start = $this->postJson("/api/quick-game/lobby/{$lobbyId}/start", $startPayload ?? [
            'matchFormat' => ['legsToWinSet' => 2, 'setsToWinMatch' => 1, 'startingScore' => 501],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ]);

        $start->assertOk()
            ->assertJsonPath('status', 'started');

        $this->assertNotNull($start->json('ffaSessionId'));

        return $lobbyId;
    }

    public function test_one_device_dart_limit_requires_bull_off_then_close_leg(): void
    {
        $lobbyId = $this->startTwoPlayerLobby([
            'matchFormat' => [
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
                'startingScore' => 501,
                'dartLimit' => 15,
            ],
            'gameType' => '501',
            'scoringMode' => 'one_device',
        ]);

        $this->assertDatabaseHas('quick_game_ffa_sessions', [
            'lobby_id' => $lobbyId,
            'dart_limit' => 15,
            'scoring_mode' => 'one_device',
        ]);

        $remaining = [];
        $state = $this->getJson("/api/quick-game/lobby/{$lobbyId}/ffa/state")->assertOk();
        $playerIds = array_map(
            static fn (array $player): int => (int) $player['playerId'],
            $state->json('players'),
        );
        foreach ($playerIds as $playerId) {
            $remaining[$playerId] = 501;
        }

        for ($i = 0; $i < 10; $i++) {
            $playerId = $playerIds[$i % 2];
            $before = $remaining[$playerId];
            $state = $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
                'playerId' => $playerId,
                'score' => 0,
                'remainingBefore' => $before,
                'remainingAfter' => $before,
                'dartsInVisit' => 3,
                'closedLeg' => false,
                'bust' => false,
                'clientVisitId' => (string) Str::uuid(),
            ]);
            $state->assertOk();
        }

        $state->assertJsonPath('meta.bullOffRequired', true);

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/visits", [
            'playerId' => $playerIds[0],
            'score' => 0,
            'remainingBefore' => 501,
            'remainingAfter' => 501,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->postJson("/api/quick-game/lobby/{$lobbyId}/ffa/close-leg", [
            'winnerPlayerId' => $playerIds[0],
        ])->assertOk()
            ->assertJsonPath('meta.bullOffRequired', false)
            ->assertJsonPath('meta.lastLegClose.reason', 'bull_off')
            ->assertJsonPath('meta.lastLegClose.winnerId', $playerIds[0]);
    }

    public function test_each_own_start_strips_dart_limit(): void
    {
        $lobbyId = $this->startTwoPlayerLobby([
            'matchFormat' => [
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
                'startingScore' => 501,
                'dartLimit' => 45,
            ],
            'gameType' => '501',
            'scoringMode' => 'each_own',
        ]);

        $this->assertDatabaseHas('quick_game_ffa_sessions', [
            'lobby_id' => $lobbyId,
            'dart_limit' => null,
            'scoring_mode' => 'each_own',
        ]);
    }
}
