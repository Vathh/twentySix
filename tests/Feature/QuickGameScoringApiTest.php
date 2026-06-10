<?php

namespace Tests\Feature;

use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameScoringApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    private User $user2;

    private Player $player1;

    private Player $player2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create(['email' => 'scoring1@test.com']);
        $this->user2 = User::factory()->create(['email' => 'scoring2@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Tomek', $this->user1->id);
        $playerService->create('Radek', $this->user2->id);

        $this->player1 = Player::where('user_id', $this->user1->id)->first();
        $this->player2 = Player::where('user_id', $this->user2->id)->first();
    }

    public function test_quick_game_scoring_flow(): void
    {
        Sanctum::actingAs($this->user1);

        $create = $this->postJson('/api/quick-game/create', [
            'player1Id' => $this->player1->id,
            'player2Id' => $this->player2->id,
            'legsCount' => 2,
        ]);
        $create->assertCreated();
        $quickGameId = $create->json('gameId');

        $startLeg = $this->postJson("/api/quick-games/{$quickGameId}/legs", [
            'player1DoubleTracked' => true,
            'player2DoubleTracked' => false,
        ]);
        $startLeg->assertOk()
            ->assertJsonPath('game.kind', 'quick')
            ->assertJsonPath('currentLeg.legNumber', 1);

        $legId = $startLeg->json('currentLeg.id');

        $visit = $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/visits", [
            'playerId' => $this->player1->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ]);
        $visit->assertOk()
            ->assertJsonPath('players.0.remaining', 441);

        $this->getJson("/api/quick-games/{$quickGameId}/scoring/state")
            ->assertOk()
            ->assertJsonPath('visits.0.score', 60);

        $close = $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/close", [
            'winnerId' => $this->player1->id,
            'players' => [
                [
                    'playerId' => $this->player1->id,
                    'doubleTracked' => true,
                    'doubleAttempts' => 6,
                    'doubleSuccesses' => 2,
                ],
                [
                    'playerId' => $this->player2->id,
                    'doubleTracked' => false,
                ],
            ],
        ]);
        $close->assertOk()
            ->assertJsonPath('game.player1LegsWon', 1);

        $this->assertDatabaseHas('game_visits', [
            'game_leg_id' => $legId,
            'player_id' => $this->player1->id,
            'score' => 60,
            'is_voided' => false,
        ]);

        $this->assertDatabaseHas('game_leg_player_stats', [
            'game_leg_id' => $legId,
            'player_id' => $this->player1->id,
            'double_tracked' => true,
            'double_attempts' => 6,
            'double_successes' => 2,
        ]);

        $leg = GameLeg::find($legId);
        $this->assertNotNull($leg->finished_at);
        $this->assertSame($this->player1->id, (int) $leg->winner_id);

        $quickGame = QuickGame::find($quickGameId);
        $this->assertSame(1, (int) $quickGame->player1_score);
        $this->assertSame(0, (int) $quickGame->player2_score);
    }

    public function test_visit_idempotency_by_client_visit_id(): void
    {
        Sanctum::actingAs($this->user1);

        $quickGameId = $this->postJson('/api/quick-game/create', [
            'player1Id' => $this->player1->id,
            'player2Id' => $this->player2->id,
        ])->json('gameId');

        $legId = $this->postJson("/api/quick-games/{$quickGameId}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ])->json('currentLeg.id');

        $clientVisitId = (string) Str::uuid();
        $payload = [
            'playerId' => $this->player1->id,
            'score' => 45,
            'remainingBefore' => 501,
            'remainingAfter' => 456,
            'dartsInVisit' => 3,
            'clientVisitId' => $clientVisitId,
        ];

        $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/visits", $payload)->assertOk();
        $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/visits", $payload)->assertOk();

        $this->assertSame(1, GameVisit::where('client_visit_id', $clientVisitId)->count());
    }

    public function test_undo_last_visit_in_open_leg(): void
    {
        Sanctum::actingAs($this->user1);

        $quickGameId = $this->postJson('/api/quick-game/create', [
            'player1Id' => $this->player1->id,
            'player2Id' => $this->player2->id,
        ])->json('gameId');

        $legId = $this->postJson("/api/quick-games/{$quickGameId}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ])->json('currentLeg.id');

        $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/visits", [
            'playerId' => $this->player1->id,
            'score' => 26,
            'remainingBefore' => 501,
            'remainingAfter' => 475,
            'dartsInVisit' => 3,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/quick-games/{$quickGameId}/legs/{$legId}/visits/undo")
            ->assertOk()
            ->assertJsonPath('visits', []);

        $this->assertDatabaseHas('game_visits', [
            'game_leg_id' => $legId,
            'is_voided' => true,
        ]);
    }
}
