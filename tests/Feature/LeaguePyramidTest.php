<?php

namespace Tests\Feature;

use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Models\League\LeagueGame;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaguePyramidTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    /** @var list<Player> */
    private array $players = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['can_create_organizations' => true]);
        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->admin->id);

        $this->organization = Organization::create(['name' => 'Klub', 'description' => 'Test']);
        $this->organization->admins()->attach($this->admin->id);

        for ($i = 1; $i <= 6; $i++) {
            $user = User::factory()->create();
            $playerService->create('Gracz '.$i, $user->id);
            $player = Player::query()->where('user_id', $user->id)->first();
            $this->players[] = $player;
        }
    }

    #[Test]
    public function admin_creates_league_assigns_roster_starts_season_and_finalizes(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('leagues.store', $this->organization), [
            'leagueName' => 'Pucharowa',
            'description' => 'Test',
            'divisions' => [
                [
                    'name' => 'Ekstraklasa',
                    'capacity' => 4,
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'promoteDirect' => 0,
                    'promotePlayoff' => 0,
                ],
                [
                    'name' => '1. liga',
                    'capacity' => 4,
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'promoteDirect' => 1,
                    'promotePlayoff' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $league = League::query()->where('name', 'Pucharowa')->first();
        $this->assertNotNull($league);
        $this->assertCount(2, $league->divisions);
        $this->attachLeaguePool($league);

        $top = $league->divisions->firstWhere('position', 0);
        $bottom = $league->divisions->firstWhere('position', 1);
        $leagueService = app(LeagueService::class);

        foreach (array_slice($this->players, 0, 3) as $player) {
            $leagueService->assignPlayer($league->id, $top->id, $player->id);
        }
        foreach (array_slice($this->players, 3, 3) as $player) {
            $leagueService->assignPlayer($league->id, $bottom->id, $player->id);
        }

        $seasonResponse = $this->post(route('league-seasons.store', $league), [
            'seasonName' => 'Sezon 1',
            'calendar_mode' => 'deadline',
            'rounds_each' => 1,
            'startDate' => '2026-09-01',
            'deadline_at' => '2026-12-01',
            'start_now' => 1,
        ]);
        $seasonResponse->assertRedirect();

        $season = LeagueSeason::query()->where('league_id', $league->id)->first();
        $this->assertSame(LeagueSeasonStatus::IN_PROGRESS, $season->status);
        $this->assertGreaterThan(0, $season->games()->count());

        $this->completeScheduledGames($season);

        $this->post(route('league-seasons.advance', $season))->assertRedirect();
        $this->completeScheduledGames($season->fresh());
        $this->post(route('league-seasons.advance', $season))->assertRedirect();
        $this->completeScheduledGames($season->fresh());
        $advance = $this->post(route('league-seasons.advance', $season));
        $advance->assertRedirect();
        $season->refresh();
        $this->assertSame(LeagueSeasonStatus::FINISHED, $season->status);

        $league->refresh();
        $top->refresh();
        $this->assertSame(4, $top->members()->count(), 'Dziura w ekstraklasie załatana z 1. ligi.');
    }

    #[Test]
    public function withdrawal_voids_all_games_and_removes_from_pyramid(): void
    {
        $this->actingAs($this->admin);
        $league = $this->seedStartedTwoPlayerLeague();
        $season = $league->seasons()->first();
        $playerId = $this->players[0]->id;

        $this->post(route('league-seasons.withdraw', $season), ['player_id' => $playerId])
            ->assertRedirect();

        $this->assertTrue(
            $season->games()->where('status', LeagueGameStatus::VOIDED->value)->exists()
        );
        $this->assertFalse($league->members()->where('player_id', $playerId)->exists());
        $this->assertNotNull(
            $season->participants()->where('player_id', $playerId)->first()?->withdrawn_at
        );
    }

    #[Test]
    public function admin_can_cancel_season_with_password_and_name_confirmation(): void
    {
        $this->actingAs($this->admin);
        $league = $this->seedStartedTwoPlayerLeague();
        $season = $league->seasons()->first();
        $playerId = $this->players[0]->id;
        $this->post(route('league-seasons.withdraw', $season), ['player_id' => $playerId]);
        $this->assertFalse($league->members()->where('player_id', $playerId)->exists());

        $this->post(route('league-seasons.cancel', $season), [
            'current_password' => 'password',
            'season_name_confirmation' => $season->name,
        ])->assertRedirect(route('leagues.show', $league));

        $this->assertDatabaseMissing('league_seasons', ['id' => $season->id]);
        $this->assertDatabaseMissing('league_games', ['league_season_id' => $season->id]);
        $this->assertTrue($league->fresh()->members()->where('player_id', $playerId)->exists());
    }

    #[Test]
    public function cancel_season_rejects_wrong_password(): void
    {
        $this->actingAs($this->admin);
        $league = $this->seedStartedTwoPlayerLeague();
        $season = $league->seasons()->first();

        $this->from(route('league-seasons.show', $season))
            ->post(route('league-seasons.cancel', $season), [
                'current_password' => 'not-the-password',
                'season_name_confirmation' => $season->name,
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('league_seasons', ['id' => $season->id]);
    }

    #[Test]
    public function league_show_disables_new_season_while_one_is_in_progress(): void
    {
        $this->actingAs($this->admin);
        $league = $this->seedStartedTwoPlayerLeague();

        $this->get(route('leagues.show', $league))
            ->assertOk()
            ->assertSee('Sezon w trakcie rozgrywek')
            ->assertSee('Nowy sezon ligowy')
            ->assertDontSee(route('league-seasons.create', $league), false);

        $this->get(route('league-seasons.create', $league))
            ->assertRedirect(route('leagues.show', $league));
    }

    #[Test]
    public function non_admin_cannot_create_league(): void
    {
        $outsider = User::factory()->create();
        app(PlayerService::class)->create('Out', $outsider->id);
        $this->actingAs($outsider);

        $this->post(route('leagues.store', $this->organization), [
            'leagueName' => 'X',
            'divisions' => [[
                'name' => 'A',
                'capacity' => 4,
                'startingScore' => 501,
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
            ]],
        ])->assertForbidden();
    }

    #[Test]
    public function matchday_season_uses_chosen_kolejka_length(): void
    {
        $this->actingAs($this->admin);

        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($this->organization->id, 'Kolejkowa', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
        $this->attachLeaguePool($league);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $this->players[2]->id);
        $leagueService->assignPlayer($league->id, $division->id, $this->players[3]->id);

        $this->post(route('league-seasons.store', $league), [
            'seasonName' => 'Jesień',
            'calendar_mode' => 'matchdays',
            'matchday_planning' => 'fixed_length',
            'matchday_length_days' => 14,
            'rounds_each' => 1,
            'startDate' => '2026-09-01',
            'start_now' => 1,
        ])->assertRedirect();

        $season = LeagueSeason::query()->where('league_id', $league->id)->first();
        $this->assertSame(14, $season->matchday_length_days);
        $this->assertCount(1, $season->matchdays);
        $matchday = $season->matchdays->first();
        $this->assertSame('2026-09-01', $matchday->window_start->format('Y-m-d'));
        $this->assertSame('2026-09-14', $matchday->window_end->format('Y-m-d'));
        $this->assertSame('2026-09-14', $season->end_date->format('Y-m-d'));
        $this->assertSame('fixed_length', $season->matchday_planning->value);
    }

    #[Test]
    public function matchday_season_splits_start_and_end_equally_when_planning_equal_span(): void
    {
        $this->actingAs($this->admin);

        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($this->organization->id, 'Ramy', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
        $this->attachLeaguePool($league);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $this->players[2]->id);
        $leagueService->assignPlayer($league->id, $division->id, $this->players[3]->id);

        $this->post(route('league-seasons.store', $league), [
            'seasonName' => 'Jesień',
            'calendar_mode' => 'matchdays',
            'matchday_planning' => 'equal_span',
            'rounds_each' => 1,
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-10',
            'start_now' => 1,
        ])->assertRedirect();

        $season = LeagueSeason::query()->where('league_id', $league->id)->first();
        $this->assertSame('equal_span', $season->matchday_planning->value);
        $this->assertCount(1, $season->matchdays);
        $matchday = $season->matchdays->first();
        $this->assertSame('2026-09-01', $matchday->window_start->format('Y-m-d'));
        $this->assertSame('2026-09-10', $matchday->window_end->format('Y-m-d'));
        $this->assertSame('2026-09-10', $season->end_date->format('Y-m-d'));
    }

    #[Test]
    public function season_with_draws_stores_best_of_format(): void
    {
        $this->actingAs($this->admin);
        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($this->organization->id, 'BO', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
        $this->attachLeaguePool($league);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $this->players[0]->id);
        $leagueService->assignPlayer($league->id, $division->id, $this->players[1]->id);

        $this->post(route('league-seasons.store', $league), [
            'seasonName' => 'Z remisami',
            'calendar_mode' => 'deadline',
            'rounds_each' => 1,
            'startDate' => '2026-09-01',
            'deadline_at' => '2026-10-01',
            'allows_draws' => 1,
            'win_length' => 6,
            'start_now' => 1,
        ])->assertRedirect();

        $season = LeagueSeason::query()->where('league_id', $league->id)->first();
        $this->assertTrue((bool) $season->allows_draws);
        $this->assertSame(6, (int) $season->win_length);
        $this->assertSame('best_of', $season->win_mode->value);
        $this->assertSame('2026-10-01', $season->end_date->format('Y-m-d'));
        $this->assertSame('2026-10-01', $season->deadline_at->toDateString());
        $game = $season->games()->first();
        $this->assertSame('best_of', $game->win_mode->value);
        $this->assertSame(6, (int) $game->win_length);
        $this->assertSame(4, (int) $game->legs_to_win_set);
    }

    #[Test]
    public function players_open_lobby_accept_and_start_scoring(): void
    {
        $this->actingAs($this->admin);
        $league = $this->seedStartedTwoPlayerLeague();
        $season = $league->seasons()->first();
        $game = $season->games()->first();
        $this->assertNotNull($game);

        $hostUser = \App\Models\Users\User::query()->findOrFail($this->players[0]->user_id);
        $opponentUser = \App\Models\Users\User::query()->findOrFail($this->players[1]->user_id);

        \Laravel\Sanctum\Sanctum::actingAs($hostUser);
        $open = $this->postJson('/api/league-games/'.$game->id.'/open-lobby');
        $open->assertOk()->assertJsonPath('status', 'lobby');

        \Laravel\Sanctum\Sanctum::actingAs($opponentUser);
        $this->postJson('/api/league-games/'.$game->id.'/accept')
            ->assertOk()
            ->assertJsonPath('opponentAccepted', true);

        \Laravel\Sanctum\Sanctum::actingAs($hostUser);
        $this->postJson('/api/league-games/'.$game->id.'/start')
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');

        $state = $this->getJson('/api/league-games/'.$game->id.'/scoring/state');
        $state->assertOk()->assertJsonPath('game.kind', 'league');

        $this->postJson('/api/league-games/'.$game->id.'/legs', [
            'player1DoubleTracked' => false,
            'player2DoubleTracked' => false,
        ])->assertOk();
    }

    private function attachLeaguePool(League $league): void
    {
        $league->relatedUsers()->syncWithoutDetaching(
            collect($this->players)->pluck('user_id')->all(),
        );
    }

    private function completeScheduledGames(LeagueSeason $season): void
    {
        $service = app(LeagueSeasonService::class);
        $games = $season->games()->where('status', LeagueGameStatus::SCHEDULED->value)->get();
        foreach ($games as $game) {
            /** @var LeagueGame $game */
            $service->recordResult($game->id, 2, 0);
        }
    }

    private function seedStartedTwoPlayerLeague(): League
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
        $this->attachLeaguePool($league);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $this->players[0]->id);
        $leagueService->assignPlayer($league->id, $division->id, $this->players[1]->id);

        $seasonService = app(LeagueSeasonService::class);
        $season = $seasonService->create(
            $league->id,
            'S',
            'deadline',
            1,
            '2026-09-01',
            '2026-10-01',
            null,
            true,
        );
        $this->assertGreaterThan(0, $season->games()->count());

        return $league->fresh(['seasons', 'members']);
    }
}
