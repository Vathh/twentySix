<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceWayfindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_season_and_tournament_share_a_place_trail(): void
    {
        $organization = Organization::create([
            'name' => 'Klub Ślad',
            'description' => 'Opis klubu',
        ]);
        $season = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Jesień śladu',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-01',
        ]);
        $live = Tournament::create([
            'name' => 'Puchar śladu',
            'season_id' => $season->id,
            'date' => '2026-10-05',
            'status' => TournamentStatus::GROUP,
        ]);
        Tournament::create([
            'name' => 'Drugi puchar',
            'season_id' => $season->id,
            'date' => '2026-11-01',
            'status' => TournamentStatus::CREATED,
        ]);

        $this->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertSee('aria-label="Gdzie jesteś"', false)
            ->assertSee('place-slot--organization is-current', false)
            ->assertSee('place-card--season', false)
            ->assertSee('Jesień śladu')
            ->assertSee('Opis klubu')
            ->assertSee('2 turnieje');

        $this->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('place-slot--organization is-ancestor', false)
            ->assertSee('place-slot--season is-current', false)
            ->assertSee(route('organizations.show', $organization), false)
            ->assertSee('Tabela sezonu')
            ->assertSee('place-card--tournament is-live', false)
            ->assertSee('place-card--tournament is-planned', false)
            ->assertSee('Puchar śladu')
            ->assertSee('Grupy')
            ->assertSee('Zaplanowany');

        $this->get(route('tournaments.show', $live))
            ->assertOk()
            ->assertSee('place-slot--organization is-ancestor', false)
            ->assertSee('place-slot--season is-ancestor', false)
            ->assertSee('place-slot--tournament is-current is-live', false)
            ->assertSee('Klub Ślad')
            ->assertSee('Jesień śladu')
            ->assertSee('Puchar śladu')
            ->assertSee('Grupy')
            ->assertSee(route('seasons.show', $season), false);
    }

    public function test_one_off_tournament_shows_only_its_own_slot(): void
    {
        $tournament = Tournament::create([
            'name' => 'Sparing bez sezonu',
            'season_id' => null,
            'date' => '2026-10-05',
            'status' => TournamentStatus::CREATED,
        ]);

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Turniej jednorazowy')
            ->assertSee('Sparing bez sezonu')
            ->assertSee('Zaplanowany')
            ->assertDontSee('place-slot--organization', false)
            ->assertDontSee('place-slot--season', false);
    }

    public function test_where_i_play_cards_repeat_the_place_mark(): void
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create('Gracz śladu', $user->id);
        $organization = Organization::create([
            'name' => 'Klub kart',
            'description' => 'Opis kart',
        ]);
        $organization->admins()->attach($user->id);
        Season::create([
            'organization_id' => $organization->id,
            'name' => 'Sezon kart',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('me.index'))
            ->assertOk()
            ->assertSee('place-card--organization', false)
            ->assertSee('place-card--season', false)
            ->assertSee('>Organizacja<', false)
            ->assertSee('>Sezon<', false)
            ->assertSee('Klub kart')
            ->assertSee('Sezon kart');
    }

    public function test_organization_and_season_show_people_counts(): void
    {
        $admin = User::factory()->create();
        app(PlayerService::class)->create('Admin śladu', $admin->id);
        $organization = Organization::create([
            'name' => 'Klub ludzi',
            'description' => 'Opis',
        ]);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->attach($admin->id);
        Player::create([
            'name' => 'Gość A',
            'organization_id' => $organization->id,
        ]);
        Player::create([
            'name' => 'Gość B',
            'organization_id' => $organization->id,
        ]);
        $season = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Sezon ludzi',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-01',
        ]);
        $season->admins()->attach($admin->id);
        Player::create([
            'name' => 'Gość sezonu',
            'season_id' => $season->id,
        ]);

        $this->actingAs($admin)
            ->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertSee('place-fact-group-label">Daty', false)
            ->assertSee('place-fact-group-label">Rozgrywki', false)
            ->assertSee('place-fact-group-label">Społeczność', false)
            ->assertSee('place-stat-label">Sezony', false)
            ->assertSee('place-stat-label">Ligi', false)
            ->assertSee('place-stat-label">Turnieje', false)
            ->assertSee('place-stat-label">Powiązani', false)
            ->assertSee('place-stat-value"><span class="score-num">1</span>', false)
            ->assertSee('place-stat-label">Goście', false)
            ->assertSee('place-stat-value"><span class="score-num">2</span>', false)
            ->assertSee(route('organizations.relatedUsers', $organization), false)
            ->assertSee(route('organizations.guests', $organization), false);

        $this->actingAs($admin)
            ->get(route('seasons.show', $season))
            ->assertOk()
            ->assertSee('place-stat-label">Powiązani', false)
            ->assertSee('place-stat-value"><span class="score-num">0</span>', false)
            ->assertSee('place-stat-label">Goście', false)
            ->assertSee('place-stat-value"><span class="score-num">1</span>', false)
            ->assertSee(route('seasons.relatedUsers', $season), false)
            ->assertSee(route('seasons.guests', $season), false);

        auth()->logout();

        $this->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertSee('place-stat-label">Powiązani', false)
            ->assertSee('place-stat-value"><span class="score-num">1</span>', false)
            ->assertDontSee(route('organizations.relatedUsers', $organization), false);
    }

    public function test_people_pages_list_members_in_rows(): void
    {
        $admin = User::factory()->create();
        app(PlayerService::class)->create('Admin śladu', $admin->id);
        $organization = Organization::create([
            'name' => 'Klub ludzi',
            'description' => 'Opis',
        ]);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->attach($admin->id);
        Player::create([
            'name' => 'Gość A',
            'organization_id' => $organization->id,
        ]);
        $season = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Sezon ludzi',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-01',
        ]);
        $season->admins()->attach($admin->id);

        $this->actingAs($admin)
            ->get(route('organizations.relatedUsers', $organization))
            ->assertOk()
            ->assertSee('place-slot--organization is-ancestor', false)
            ->assertSee('Powiązani użytkownicy')
            ->assertSee('people-row', false)
            ->assertSee('Szukaj konta', false)
            ->assertSee('Admin śladu')
            ->assertDontSee('Wyszukiwanie użytkowników');

        $this->actingAs($admin)
            ->get(route('organizations.guests', $organization))
            ->assertOk()
            ->assertSee('Gość A')
            ->assertSee('Imię gościa', false)
            ->assertSee('people-letter', false)
            ->assertSee('aria-label="Usuń"', false)
            ->assertDontSee('Dodawanie graczy');

        $this->actingAs($admin)
            ->get(route('seasons.relatedUsers', $season))
            ->assertOk()
            ->assertSee('place-slot--season is-ancestor', false)
            ->assertSee('place-slot--season is-current', false)
            ->assertSee(route('organizations.show', $organization), false);
    }
}
