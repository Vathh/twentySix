<?php

namespace Tests\Feature;

use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueSeasonShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_league_season_show(): void
    {
        $season = $this->createDraftSeason();

        $this->getJson('/api/league-seasons/'.$season->id)->assertUnauthorized();
    }

    public function test_league_season_show_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/league-seasons/999999')->assertNotFound();
    }

    public function test_started_season_returns_standings_and_games(): void
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $admin->id);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $playerService->create('Anna', $userA->id);
        $playerService->create('Bartek', $userB->id);
        $playerA = Player::query()->where('user_id', $userA->id)->first();
        $playerB = Player::query()->where('user_id', $userB->id)->first();

        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->attach([$userA->id, $userB->id]);

        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($organization->id, 'Mini', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
        $league->relatedUsers()->attach([$userA->id, $userB->id]);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $playerA->id);
        $leagueService->assignPlayer($league->id, $division->id, $playerB->id);

        $season = app(LeagueSeasonService::class)->create(
            $league->id,
            'Sezon 1',
            'deadline',
            1,
            now()->toDateString(),
            now()->addMonth()->toDateString(),
            null,
            true,
        );

        Sanctum::actingAs($userA);
        $this->getJson('/api/league-seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('season.name', 'Sezon 1')
            ->assertJsonPath('season.status', 'in_progress')
            ->assertJsonPath('season.allowsDraws', false)
            ->assertJsonPath('season.formatLabel', 'First to 2')
            ->assertJsonPath('league.name', 'Mini')
            ->assertJsonPath('organization.name', 'Klub')
            ->assertJsonCount(1, 'divisions')
            ->assertJsonPath('divisions.0.name', 'Jedyna')
            ->assertJsonCount(2, 'divisions.0.standings')
            ->assertJsonCount(1, 'divisions.0.games')
            ->assertJsonPath('divisions.0.games.0.status', 'scheduled')
            ->assertJsonMissingPath('season.url');
    }

    private function createDraftSeason(): LeagueSeason
    {
        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $league = League::create([
            'organization_id' => $organization->id,
            'name' => 'Liga',
            'description' => '',
        ]);

        return LeagueSeason::create([
            'league_id' => $league->id,
            'name' => 'Szkic',
            'status' => LeagueSeasonStatus::DRAFT,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
    }
}
