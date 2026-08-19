<?php

namespace Tests\Feature;

use App\Enums\TournamentInvitationStatus;
use App\Models\League\League;
use App\Models\League\LeagueDivision;
use App\Models\League\LeagueDivisionMember;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyCompetitionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Gracz'): User
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create($name, $user->id);

        return $user->fresh();
    }

    public function test_guest_cannot_view_web_hub(): void
    {
        $this->get(route('me.index'))->assertRedirect('/login');
    }

    public function test_guest_cannot_fetch_api_hub(): void
    {
        $this->getJson('/api/me/competitions')->assertUnauthorized();
    }

    public function test_outsider_sees_empty_lists_on_web_and_api(): void
    {
        $outsider = $this->makeUser('Outsider');
        $this->seedForeignCompetition();

        $this->withoutVite();
        $this->actingAs($outsider)
            ->get(route('me.index'))
            ->assertOk()
            ->assertSee('Gdzie gram')
            ->assertSee('Nie jesteś powiązany z żadnym trwającym sezonem turniejowym.')
            ->assertSee('Nie jesteś w żadnej lidze piramidowej.')
            ->assertSee('Nie jesteś powiązany z żadną organizacją.')
            ->assertDontSee('Sezon A')
            ->assertDontSee('Liga A')
            ->assertDontSee('Org A');

        Sanctum::actingAs($outsider);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonPath('seasons', [])
            ->assertJsonPath('leagues', [])
            ->assertJsonPath('organizations', [])
            ->assertJsonMissingPath('seasons.0.url');
    }

    public function test_org_admin_sees_organization_seasons_and_leagues_as_admin(): void
    {
        $admin = $this->makeUser('Admin');
        $org = Organization::create(['name' => 'Klub Admina', 'description' => 'Opis']);
        $org->admins()->attach($admin->id);
        $season = $this->createCurrentSeason($org, 'Sezon klubowy');
        $this->createLeague($org, 'Liga klubowa', 'Ekstraklasa');

        Sanctum::actingAs($admin);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.id', $season->id)
            ->assertJsonPath('seasons.0.role', 'admin')
            ->assertJsonPath('seasons.0.roleLabel', 'Admin')
            ->assertJsonCount(1, 'leagues')
            ->assertJsonPath('leagues.0.role', 'admin')
            ->assertJsonPath('leagues.0.roleLabel', 'Admin')
            ->assertJsonCount(1, 'organizations')
            ->assertJsonPath('organizations.0.id', $org->id)
            ->assertJsonPath('organizations.0.role', 'admin')
            ->assertJsonMissingPath('organizations.0.url');

        $this->withoutVite();
        $this->actingAs($admin)
            ->get(route('me.index'))
            ->assertOk()
            ->assertSee('Sezon klubowy')
            ->assertSee('Liga klubowa')
            ->assertSee('Klub Admina')
            ->assertSee(route('seasons.show', $season), false)
            ->assertSee(route('organizations.show', $org), false);
    }

    public function test_related_org_member_sees_organization_but_not_unrelated_seasons(): void
    {
        $member = $this->makeUser('Skład');
        $org = Organization::create(['name' => 'Org składu', 'description' => '']);
        $org->relatedUsers()->attach($member->id);
        $this->createCurrentSeason($org, 'Sezon bez składu');

        Sanctum::actingAs($member);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonPath('seasons', [])
            ->assertJsonPath('leagues', [])
            ->assertJsonCount(1, 'organizations')
            ->assertJsonPath('organizations.0.role', 'member')
            ->assertJsonPath('organizations.0.roleLabel', 'Skład');
    }

    public function test_season_related_user_sees_season_without_parent_organization(): void
    {
        $member = $this->makeUser('Sezonowy');
        $org = Organization::create(['name' => 'Org sezonu', 'description' => '']);
        $season = $this->createCurrentSeason($org, 'Sezon powiązany');
        $season->relatedUsers()->attach($member->id);

        Sanctum::actingAs($member);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.id', $season->id)
            ->assertJsonPath('seasons.0.role', 'member')
            ->assertJsonPath('seasons.0.roleLabel', 'Skład')
            ->assertJsonPath('organizations', []);
    }

    public function test_accepted_tournament_invitation_includes_season_pending_does_not(): void
    {
        $accepted = $this->makeUser('Zaakceptowany');
        $pending = $this->makeUser('Oczekujący');
        $org = Organization::create(['name' => 'Org turnieju', 'description' => '']);
        $season = $this->createCurrentSeason($org, 'Sezon turniejowy');
        $tournament = Tournament::create([
            'season_id' => $season->id,
            'name' => 'Open',
            'date' => '2026-03-01',
        ]);
        TournamentInvitation::create([
            'tournament_id' => $tournament->id,
            'user_id' => $accepted->id,
            'invited_by' => $accepted->id,
            'status' => TournamentInvitationStatus::ACCEPTED,
        ]);
        TournamentInvitation::create([
            'tournament_id' => $tournament->id,
            'user_id' => $pending->id,
            'invited_by' => $accepted->id,
            'status' => TournamentInvitationStatus::PENDING,
        ]);

        Sanctum::actingAs($accepted);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.id', $season->id);

        Sanctum::actingAs($pending);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonPath('seasons', []);
    }

    public function test_league_player_sees_league_with_division_not_parent_organization(): void
    {
        $playerUser = $this->makeUser('Zawodnik');
        $player = Player::query()->where('user_id', $playerUser->id)->first();
        $org = Organization::create(['name' => 'Org ligi', 'description' => '']);
        $league = $this->createLeague($org, 'Pucharowa', '1. liga');
        $division = $league->divisions()->first();
        LeagueDivisionMember::create([
            'league_id' => $league->id,
            'league_division_id' => $division->id,
            'player_id' => $player->id,
        ]);

        Sanctum::actingAs($playerUser);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonPath('seasons', [])
            ->assertJsonCount(1, 'leagues')
            ->assertJsonPath('leagues.0.id', $league->id)
            ->assertJsonPath('leagues.0.divisionName', '1. liga')
            ->assertJsonPath('leagues.0.role', 'member')
            ->assertJsonPath('leagues.0.roleLabel', 'Zawodnik')
            ->assertJsonPath('organizations', []);
    }

    public function test_hub_lists_only_seasons_currently_in_progress(): void
    {
        $member = $this->makeUser('Sezonowy');
        $org = Organization::create(['name' => 'Org dat', 'description' => '']);
        $current = $this->createCurrentSeason($org, 'Sezon trwający');
        $finished = Season::create([
            'organization_id' => $org->id,
            'name' => 'Sezon zakończony',
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);
        $upcoming = Season::create([
            'organization_id' => $org->id,
            'name' => 'Sezon przyszły',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $current->relatedUsers()->attach($member->id);
        $finished->relatedUsers()->attach($member->id);
        $upcoming->relatedUsers()->attach($member->id);

        Sanctum::actingAs($member);
        $this->getJson('/api/me/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'seasons')
            ->assertJsonPath('seasons.0.id', $current->id)
            ->assertJsonPath('seasons.0.name', 'Sezon trwający');

        $this->withoutVite();
        $this->actingAs($member)
            ->get(route('me.index'))
            ->assertOk()
            ->assertSee('Sezon trwający')
            ->assertDontSee('Sezon zakończony')
            ->assertDontSee('Sezon przyszły');
    }

    private function seedForeignCompetition(): void
    {
        $org = Organization::create(['name' => 'Org A', 'description' => '']);
        $this->createCurrentSeason($org, 'Sezon A');
        $this->createLeague($org, 'Liga A', 'Ekstraklasa');
    }

    private function createCurrentSeason(Organization $organization, string $name): Season
    {
        return Season::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
    }

    private function createLeague(Organization $organization, string $name, string $divisionName): League
    {
        $league = League::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'description' => '',
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
