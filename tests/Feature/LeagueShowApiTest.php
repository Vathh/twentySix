<?php

namespace Tests\Feature;

use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Models\League\LeagueDivision;
use App\Models\League\LeagueDivisionMember;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_league_show(): void
    {
        $league = $this->createLeague();

        $this->getJson('/api/leagues/'.$league->id)->assertUnauthorized();
    }

    public function test_league_show_returns_pyramid_and_seasons(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $user = User::factory()->create();
        app(PlayerService::class)->create('Anna', $user->id);
        $player = Player::query()->where('user_id', $user->id)->first();

        $league = $this->createLeague('Pucharowa', 'Ekstraklasa');
        $division = $league->divisions()->first();
        LeagueDivisionMember::create([
            'league_id' => $league->id,
            'league_division_id' => $division->id,
            'player_id' => $player->id,
        ]);
        LeagueSeason::create([
            'league_id' => $league->id,
            'name' => 'Sezon 1',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->getJson('/api/leagues/'.$league->id)
            ->assertOk()
            ->assertJsonPath('league.name', 'Pucharowa')
            ->assertJsonPath('organization.name', 'Klub')
            ->assertJsonPath('activeSeason.name', 'Sezon 1')
            ->assertJsonPath('activeSeason.status', 'in_progress')
            ->assertJsonCount(1, 'divisions')
            ->assertJsonPath('divisions.0.name', 'Ekstraklasa')
            ->assertJsonPath('divisions.0.memberCount', 1)
            ->assertJsonPath('divisions.0.members.0.playerName', 'Anna')
            ->assertJsonPath('divisions.0.members.0.userId', $user->id)
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.statusLabel', 'W trakcie')
            ->assertJsonPath('seasons.0.statusVariant', 'live')
            ->assertJsonMissingPath('league.url');
    }

    public function test_league_show_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/leagues/999999')->assertNotFound();
    }

    private function createLeague(string $name = 'Liga', string $divisionName = 'Ekstraklasa'): League
    {
        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $league = League::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'description' => 'Opis ligi',
        ]);
        LeagueDivision::create([
            'league_id' => $league->id,
            'position' => 0,
            'name' => $divisionName,
            'capacity' => 8,
        ]);

        return $league->fresh('divisions');
    }
}
