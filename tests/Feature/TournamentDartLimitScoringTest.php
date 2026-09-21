<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsTournamentTablet;
use Tests\TestCase;

class TournamentDartLimitScoringTest extends TestCase
{
    use ActsAsTournamentTablet;
    use RefreshDatabase;

    private Tournament $tournament;

    private Player $player1;

    private Player $player2;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['email' => 'dart-limit-tournament@test.com']);
        app(PlayerService::class)->create('User', $user->id);

        $organization = Organization::create(['name' => 'Dart Limit Org', 'description' => 'Test']);
        $organization->admins()->attach($user->id);

        $season = Season::create([
            'name' => 'Test Season',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $season->admins()->attach($user->id);

        $this->tournament = Tournament::create([
            'name' => 'Dart Limit Scoring',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);

        $this->player1 = Player::where('user_id', $user->id)->first();
        $this->player1->update(['season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->player2 = Player::create([
            'name' => 'Player2',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $this->actAsTournamentTablet($this->tournament);
    }

    public function test_dart_limit_auto_closes_when_exactly_one_player_is_above_threshold(): void
    {
        $game = $this->createLimitedGame(lossThreshold: 50);
        $legId = $this->startLeg($game->id);

        $state = $this->playAlternatingVisits($game->id, $legId, [
            [$this->player1->id, 0],
            [$this->player2->id, 100],
            [$this->player1->id, 0],
            [$this->player2->id, 100],
            [$this->player1->id, 0],
            [$this->player2->id, 100],
            [$this->player1->id, 0],
            [$this->player2->id, 100],
            [$this->player1->id, 0],
            [$this->player2->id, 100],
        ], 501);

        $state->assertJsonPath('meta.lastLegClose.reason', 'loss_threshold');
        $state->assertJsonPath('meta.lastLegClose.winnerId', $this->player2->id);
        $state->assertJsonPath('game.player1LegsWon', 0);
        $state->assertJsonPath('game.player2LegsWon', 1);

        $this->assertDatabaseHas('game_legs', [
            'id' => $legId,
            'winner_id' => $this->player2->id,
            'close_reason' => 'loss_threshold',
        ]);
    }

    public function test_dart_limit_requires_bull_off_when_both_above_threshold(): void
    {
        $game = $this->createLimitedGame(lossThreshold: 50);
        $legId = $this->startLeg($game->id);

        $state = $this->playAlternatingVisits($game->id, $legId, [
            [$this->player1->id, 0],
            [$this->player2->id, 0],
            [$this->player1->id, 0],
            [$this->player2->id, 0],
            [$this->player1->id, 0],
            [$this->player2->id, 0],
            [$this->player1->id, 0],
            [$this->player2->id, 0],
            [$this->player1->id, 0],
            [$this->player2->id, 0],
        ], 501);

        $state->assertJsonPath('meta.bullOffRequired', true);
        $this->assertTrue(GameLeg::find($legId)->isOpen());

        $this->postJson("/api/group-games/{$game->id}/legs/{$legId}/visits", [
            'playerId' => $this->player1->id,
            'score' => 0,
            'remainingBefore' => 501,
            'remainingAfter' => 501,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->postJson("/api/group-games/{$game->id}/legs/{$legId}/close", [
            'winnerId' => $this->player1->id,
            'reason' => 'bull_off',
            'players' => [
                ['playerId' => $this->player1->id, 'doubleTracked' => false],
                ['playerId' => $this->player2->id, 'doubleTracked' => false],
            ],
        ])->assertOk()
            ->assertJsonPath('meta.lastLegClose.reason', 'bull_off')
            ->assertJsonPath('meta.lastLegClose.winnerId', $this->player1->id);

        $this->assertFalse(GameLeg::find($legId)->isOpen());
        $this->assertSame('bull_off', GameLeg::find($legId)->close_reason);
    }

    private function createLimitedGame(int $lossThreshold): Game
    {
        return Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
            'starting_score' => 501,
            'legs_to_win_set' => 2,
            'sets_to_win_match' => 1,
            'dart_limit' => 15,
            'loss_threshold' => $lossThreshold,
        ]);
    }

    private function startLeg(int $gameId): int
    {
        $start = $this->postJson("/api/group-games/{$gameId}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ]);
        $start->assertOk();

        return (int) $start->json('currentLeg.id');
    }

    private function playAlternatingVisits(int $gameId, int $legId, array $sequence, int $starting)
    {
        $remaining = [];
        $response = null;
        foreach ($sequence as [$playerId, $score]) {
            $before = $remaining[$playerId] ?? $starting;
            $after = max(0, $before - $score);
            $response = $this->postJson("/api/group-games/{$gameId}/legs/{$legId}/visits", [
                'playerId' => $playerId,
                'score' => $score,
                'remainingBefore' => $before,
                'remainingAfter' => $after,
                'dartsInVisit' => 3,
                'closedLeg' => false,
                'bust' => false,
                'clientVisitId' => (string) Str::uuid(),
            ]);
            $response->assertOk();
            $remaining[$playerId] = $after;
        }

        return $response;
    }
}
