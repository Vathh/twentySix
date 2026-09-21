<?php

namespace Tests\Feature;

use App\Domain\GameScoring\MatchFormat;
use App\Enums\GameKind;
use App\Enums\GameStatus;
use App\Enums\LeagueGameStatus;
use App\Enums\TournamentStatus;
use App\Events\GameScoringCancelled;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameVisit;
use App\Models\League\LeagueGame;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Organization $organization;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['can_create_organizations' => true]);
        $this->regularUser = User::factory()->create();
        app(PlayerService::class)->create('Admin', $this->admin->id);
        app(PlayerService::class)->create('User', $this->regularUser->id);

        $this->organization = Organization::create(['name' => 'Org', 'description' => '']);
        $this->organization->admins()->attach($this->admin->id);

        $this->season = Season::create([
            'name' => 'Season',
            'organization_id' => $this->organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $this->season->admins()->attach($this->admin->id);
    }

    public function test_admin_cancels_in_progress_group_game(): void
    {
        Event::fake([GameScoringCancelled::class]);
        $this->actingAs($this->admin);

        $game = $this->createInProgressGroupGame();
        $this->seedVisit($game->id, null, null, $game->player1_id);

        $this->from(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->post(route('games.cancel', ['type' => 'group', 'id' => $game->id]), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->assertSessionHas('success');

        $game->refresh();
        $this->assertSame(GameStatus::SCHEDULED, $game->status);
        $this->assertSame(0, $game->player1_score);
        $this->assertSame(0, $game->player2_score);
        $this->assertNull($game->winner_id);
        $this->assertDatabaseMissing('game_legs', ['game_id' => $game->id]);
        $this->assertDatabaseCount('game_visits', 0);

        Event::assertDispatched(GameScoringCancelled::class, function (GameScoringCancelled $event) use ($game) {
            return $event->broadcastAs() === 'game.cancelled'
                && $event->context->kind === GameKind::GROUP
                && $event->context->gameId === $game->id;
        });
    }

    public function test_admin_cancels_in_progress_playoff_game(): void
    {
        Event::fake([GameScoringCancelled::class]);
        $this->actingAs($this->admin);

        $game = $this->createInProgressPlayoffGame();
        $this->seedVisit(null, $game->id, null, $game->player1_id);

        $this->post(route('games.cancel', ['type' => 'playoff', 'id' => $game->id]), [
            'current_password' => 'password',
        ])->assertRedirect(route('games.show', ['type' => 'playoff', 'id' => $game->id]));

        $game->refresh();
        $this->assertSame(GameStatus::SCHEDULED, $game->status);
        $this->assertDatabaseMissing('game_legs', ['playoff_game_id' => $game->id]);
        Event::assertDispatched(GameScoringCancelled::class);
    }

    public function test_cannot_cancel_finished_group_game(): void
    {
        $this->actingAs($this->admin);
        $game = $this->createInProgressGroupGame();
        $game->update([
            'status' => GameStatus::FINISHED,
            'player1_score' => 2,
            'player2_score' => 0,
            'winner_id' => $game->player1_id,
        ]);

        $this->from(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->post(route('games.cancel', ['type' => 'group', 'id' => $game->id]), [
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(GameStatus::FINISHED, $game->fresh()->status);
    }

    public function test_cancel_rejects_wrong_password(): void
    {
        $this->actingAs($this->admin);
        $game = $this->createInProgressGroupGame();

        $this->from(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->post(route('games.cancel', ['type' => 'group', 'id' => $game->id]), [
                'current_password' => 'wrong',
            ])
            ->assertRedirect(route('games.show', ['type' => 'group', 'id' => $game->id]))
            ->assertSessionHasErrors('current_password');

        $this->assertSame(GameStatus::IN_PROGRESS, $game->fresh()->status);
    }

    public function test_non_admin_cannot_cancel_group_game(): void
    {
        $this->actingAs($this->regularUser);
        $game = $this->createInProgressGroupGame();

        $this->post(route('games.cancel', ['type' => 'group', 'id' => $game->id]), [
            'current_password' => 'password',
        ])->assertForbidden();

        $this->assertSame(GameStatus::IN_PROGRESS, $game->fresh()->status);
    }

    public function test_admin_cancels_league_game_in_progress(): void
    {
        Event::fake([GameScoringCancelled::class]);
        $this->actingAs($this->admin);
        $game = $this->createLeagueGame(LeagueGameStatus::IN_PROGRESS);
        $this->seedVisit(null, null, $game->id, $game->player1_id);

        $this->post(route('league-games.cancel', $game), [
            'current_password' => 'password',
        ])->assertRedirect(route('league-games.show', $game));

        $game->refresh();
        $this->assertSame(LeagueGameStatus::SCHEDULED, $game->status);
        $this->assertNull($game->lobby_host_player_id);
        $this->assertNull($game->scoring_host_player_id);
        $this->assertDatabaseMissing('game_legs', ['league_game_id' => $game->id]);
        Event::assertDispatched(GameScoringCancelled::class);
    }

    public function test_admin_cancels_league_lobby(): void
    {
        $this->actingAs($this->admin);
        $game = $this->createLeagueGame(LeagueGameStatus::LOBBY);

        $this->post(route('league-games.cancel', $game), [
            'current_password' => 'password',
        ])->assertRedirect(route('league-games.show', $game));

        $this->assertSame(LeagueGameStatus::SCHEDULED, $game->fresh()->status);
    }

    public function test_scoring_state_returns_conflict_after_group_cancel(): void
    {
        $this->actingAs($this->admin);
        $game = $this->createInProgressGroupGame();
        $this->seedVisit($game->id, null, null, $game->player1_id);

        $this->post(route('games.cancel', ['type' => 'group', 'id' => $game->id]), [
            'current_password' => 'password',
        ])->assertRedirect();

        $this->getJson("/api/group-games/{$game->id}/scoring/state")
            ->assertStatus(409);
    }

    private function createInProgressGroupGame(): Game
    {
        $tournament = Tournament::create([
            'name' => 'Turniej',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);
        $tournament->admins()->attach($this->admin->id);

        $p1 = Player::query()->where('user_id', $this->admin->id)->firstOrFail();
        $p2 = Player::query()->where('user_id', $this->regularUser->id)->firstOrFail();

        return Game::create(array_merge([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_score' => 1,
            'player2_score' => 0,
            'group_number' => 1,
            'status' => GameStatus::IN_PROGRESS,
        ], MatchFormat::default()->toDatabaseColumns()));
    }

    private function createInProgressPlayoffGame(): PlayoffGame
    {
        $tournament = Tournament::create([
            'name' => 'Playoff',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::PLAYOFF,
        ]);
        $tournament->admins()->attach($this->admin->id);

        $p1 = Player::query()->where('user_id', $this->admin->id)->firstOrFail();
        $p2 = Player::query()->where('user_id', $this->regularUser->id)->firstOrFail();

        return PlayoffGame::create(array_merge([
            'tournament_id' => $tournament->id,
            'round' => 'QUARTER',
            'slot' => 'QF_1',
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'status' => GameStatus::IN_PROGRESS,
        ], MatchFormat::default()->toDatabaseColumns()));
    }

    private function createLeagueGame(LeagueGameStatus $status): LeagueGame
    {
        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($this->organization->id, 'Mini', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);

        $p1 = Player::query()->where('user_id', $this->admin->id)->firstOrFail();
        $p2 = Player::query()->where('user_id', $this->regularUser->id)->firstOrFail();
        $league->relatedUsers()->syncWithoutDetaching([$p1->user_id, $p2->user_id]);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $p1->id);
        $leagueService->assignPlayer($league->id, $division->id, $p2->id);

        $season = app(LeagueSeasonService::class)->create(
            $league->id,
            'S',
            'deadline',
            1,
            '2026-09-01',
            '2026-10-01',
            null,
            true,
        );

        /** @var LeagueGame $game */
        $game = $season->games()->firstOrFail();
        $game->update([
            'status' => $status,
            'lobby_host_player_id' => $status === LeagueGameStatus::SCHEDULED ? null : $p1->id,
            'scoring_host_player_id' => $status === LeagueGameStatus::IN_PROGRESS ? $p1->id : null,
        ]);

        return $game->fresh();
    }

    private function seedVisit(?int $gameId, ?int $playoffGameId, ?int $leagueGameId, int $playerId): void
    {
        $leg = GameLeg::create([
            'game_id' => $gameId,
            'playoff_game_id' => $playoffGameId,
            'league_game_id' => $leagueGameId,
            'leg_number' => 1,
            'started_at' => now(),
        ]);
        GameVisit::create([
            'game_leg_id' => $leg->id,
            'player_id' => $playerId,
            'visit_number' => 1,
            'score' => 60,
            'remaining_before' => 501,
            'remaining_after' => 441,
            'darts_in_visit' => 3,
            'closed_leg' => false,
            'bust' => false,
            'is_voided' => false,
            'client_visit_id' => 'cancel-test-visit',
        ]);
    }
}
