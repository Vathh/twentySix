<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\PlayoffSlot;
use App\Enums\TournamentStatus;
use App\Enums\WinnerDestinationSlot;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\GroupStanding\GroupStanding;
use App\Models\League\League;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentGameScoringFinalizeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tournament $tournament;

    private Player $player1;

    private Player $player2;

    private Player $player3;

    private Player $player4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'scoring-tournament@test.com']);
        $playerService = app(PlayerService::class);
        $playerService->create('User', $this->user->id);

        $league = League::create(['name' => 'Test League', 'description' => 'Test']);
        $league->admins()->attach($this->user->id);

        $season = Season::create([
            'name' => 'Test Season',
            'league_id' => $league->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $season->admins()->attach($this->user->id);

        $this->tournament = Tournament::create([
            'name' => 'Scoring Finalize Test',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);

        $smallScheme = PointScheme::create([
            'name' => 'od 2 do 8 osob',
            'min_players' => 2,
            'max_players' => 8,
        ]);

        PointSchemeRule::create(['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 2, 'points' => 2]);
        PointSchemeRule::create(['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 1, 'points' => 4]);
        PointSchemeRule::create(['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 6]);
        PointSchemeRule::create(['point_scheme_id' => $smallScheme->id, 'elimination_stage' => GameStage::SEMI->value, 'place' => null, 'points' => 8]);
        $this->tournament->update(['point_scheme_id' => $smallScheme->id]);

        $this->player1 = Player::where('user_id', $this->user->id)->first();
        $this->player1->update(['season_id' => $season->id, 'league_id' => $league->id]);
        $this->player2 = Player::create([
            'name' => 'Player2',
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]);
        $this->player3 = Player::create([
            'name' => 'Player3',
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]);
        $this->player4 = Player::create([
            'name' => 'Player4',
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_group_scoring_close_second_leg_updates_standings(): void
    {
        GroupStanding::create([
            'tournament_id' => $this->tournament->id,
            'group_number' => 1,
            'player_id' => $this->player1->id,
        ]);
        GroupStanding::create([
            'tournament_id' => $this->tournament->id,
            'group_number' => 1,
            'player_id' => $this->player2->id,
        ]);

        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player3->id,
            'player2_id' => $this->player4->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );
        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $game->refresh();
        $this->assertSame(GameStatus::FINISHED, $game->status);
        $this->assertSame(2, (int) $game->player1_score);
        $this->assertSame(0, (int) $game->player2_score);
        $this->assertSame($this->player1->id, (int) $game->winner_id);

        $this->assertDatabaseHas('group_standings', [
            'tournament_id' => $this->tournament->id,
            'player_id' => $this->player1->id,
            'games_played' => 1,
            'games_won' => 1,
            'legs_won' => 2,
            'legs_lost' => 0,
            'points' => 1,
        ]);
        $this->assertDatabaseHas('group_standings', [
            'tournament_id' => $this->tournament->id,
            'player_id' => $this->player2->id,
            'games_played' => 1,
            'games_lost' => 1,
            'legs_won' => 0,
            'legs_lost' => 2,
        ]);
    }

    public function test_group_scoring_undo_reopens_closed_leg_while_match_in_progress(): void
    {
        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $game->refresh();
        $this->assertSame(GameStatus::IN_PROGRESS, $game->status);
        $this->assertSame(1, (int) $game->player1_score);

        $legId = GameLeg::where('game_id', $game->id)->orderByDesc('leg_number')->value('id');

        $this->postJson("/api/group-games/{$game->id}/legs/{$legId}/visits/undo")
            ->assertOk()
            ->assertJsonPath('currentLeg.open', true);

        $game->refresh();
        $this->assertSame(GameStatus::IN_PROGRESS, $game->status);
        $this->assertSame(0, (int) $game->player1_score);
        $this->assertNull($game->winner_id);
    }

    public function test_playoff_scoring_close_second_leg_advances_winner(): void
    {
        $quarterFinal = PlayoffGame::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'round' => GameStage::QUARTER,
            'slot' => PlayoffSlot::QF_1,
            'winner_destination_slot' => WinnerDestinationSlot::SEMI_1_A,
            'status' => GameStatus::SCHEDULED,
        ]);

        PlayoffGame::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => null,
            'player2_id' => null,
            'round' => GameStage::SEMI,
            'slot' => PlayoffSlot::SEMI_1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->closeScoringLeg(
            'playoff-games',
            $quarterFinal->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );
        $this->closeScoringLeg(
            'playoff-games',
            $quarterFinal->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $quarterFinal->refresh();
        $this->assertSame(GameStatus::FINISHED, $quarterFinal->status);
        $this->assertSame($this->player1->id, (int) $quarterFinal->winner_id);

        $semi = PlayoffGame::where('tournament_id', $this->tournament->id)
            ->where('slot', PlayoffSlot::SEMI_1)
            ->first();

        $this->assertSame($this->player1->id, (int) $semi->player1_id);
    }

    public function test_achievements_only_post_on_finished_group_game(): void
    {
        GroupStanding::create([
            'tournament_id' => $this->tournament->id,
            'group_number' => 1,
            'player_id' => $this->player1->id,
        ]);
        GroupStanding::create([
            'tournament_id' => $this->tournament->id,
            'group_number' => 1,
            'player_id' => $this->player2->id,
        ]);

        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player3->id,
            'player2_id' => $this->player4->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );
        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $response = $this->postJson('/api/game/update', [
            'game' => [
                'id' => $game->id,
                'type' => GameType::GROUP->value,
                'tournamentId' => $this->tournament->id,
                'groupNumber' => 1,
                'player1Id' => $this->player1->id,
                'player2Id' => $this->player2->id,
                'player1Score' => 2,
                'player2Score' => 0,
                'winnerId' => $this->player1->id,
            ],
            'achievements' => [
                [
                    'playerId' => $this->player1->id,
                    'tournamentId' => $this->tournament->id,
                    'type' => 'max',
                    'value' => null,
                ],
            ],
            'legs' => [],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('achievements', [
            'player_id' => $this->player1->id,
            'tournament_id' => $this->tournament->id,
            'type' => 'max',
        ]);
    }

    public function test_rejects_bulk_update_on_already_finished_game(): void
    {
        Sanctum::actingAs($this->user);

        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player3->id,
            'player2_id' => $this->player4->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );
        $this->closeScoringLeg(
            'group-games',
            $game->id,
            $this->player1->id,
            $this->player1->id,
            $this->player2->id,
        );

        $response = $this->postJson('/api/game/update', [
            'game' => [
                'id' => $game->id,
                'type' => GameType::GROUP->value,
                'tournamentId' => $this->tournament->id,
                'groupNumber' => 1,
                'player1Id' => $this->player1->id,
                'player2Id' => $this->player2->id,
                'player1Score' => 2,
                'player2Score' => 0,
                'winnerId' => $this->player1->id,
            ],
            'achievements' => [],
            'legs' => [],
        ]);

        $response->assertOk()->assertJson(['success' => false]);
    }

    private function closeScoringLeg(
        string $prefix,
        int $gameId,
        int $winnerId,
        int $player1Id,
        int $player2Id,
    ): void {
        $start = $this->postJson("/api/{$prefix}/{$gameId}/legs", [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ]);
        $start->assertOk();
        $legId = $start->json('currentLeg.id');

        $this->postJson("/api/{$prefix}/{$gameId}/legs/{$legId}/visits", [
            'playerId' => $winnerId,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 3,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/{$prefix}/{$gameId}/legs/{$legId}/close", [
            'winnerId' => $winnerId,
            'players' => [
                ['playerId' => $player1Id, 'doubleTracked' => false],
                ['playerId' => $player2Id, 'doubleTracked' => false],
            ],
        ])->assertOk();
    }

    public function test_group_scoring_upserts_visit_by_client_visit_id(): void
    {
        Sanctum::actingAs($this->user);

        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $start = $this->postJson("/api/group-games/{$game->id}/legs", [
            'player1DoubleTracked' => true,
            'player2DoubleTracked' => true,
        ]);
        $start->assertOk()
            ->assertJsonPath('format', 'h2h')
            ->assertJsonPath('meta.kind', 'tournament_group')
            ->assertJsonPath('turn.legNumber', 1);
        $legId = $start->json('currentLeg.id');
        $clientVisitId = (string) Str::uuid();

        $this->postJson("/api/group-games/{$game->id}/legs/{$legId}/visits", [
            'playerId' => $this->player1->id,
            'score' => 60,
            'remainingBefore' => 501,
            'remainingAfter' => 441,
            'dartsInVisit' => 1,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => $clientVisitId,
        ])->assertOk();

        $this->postJson("/api/group-games/{$game->id}/legs/{$legId}/visits", [
            'playerId' => $this->player1->id,
            'score' => 100,
            'remainingBefore' => 501,
            'remainingAfter' => 401,
            'dartsInVisit' => 2,
            'closedLeg' => false,
            'bust' => false,
            'clientVisitId' => $clientVisitId,
        ])->assertOk();

        $this->assertDatabaseCount('game_visits', 1);
        $this->assertDatabaseHas('game_visits', [
            'game_leg_id' => $legId,
            'client_visit_id' => $clientVisitId,
            'score' => 100,
            'darts_in_visit' => 2,
            'remaining_after' => 401,
        ]);
    }
}
