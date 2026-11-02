<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Organization\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_organizations(): void
    {
        $this->getJson('/api/organizations')->assertUnauthorized();
    }

    public function test_guest_cannot_list_seasons(): void
    {
        $this->getJson('/api/seasons')->assertUnauthorized();
    }

    public function test_guest_cannot_list_tournaments(): void
    {
        $this->getJson('/api/tournaments')->assertUnauthorized();
    }

    public function test_organizations_index_returns_items_without_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Organization::create(['name' => 'Organizacja Test', 'description' => 'Opis']);

        $this->getJson('/api/organizations?page=1')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    ['id', 'title', 'subtitle', 'logoUrl', 'initials', 'stats'],
                ],
                'has_more',
                'summary',
            ])
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('items.0.title', 'Organizacja Test')
            ->assertJsonMissingPath('items.0.url');
    }

    public function test_organizations_index_paginates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 1; $i <= OrganizationRepository::INDEX_PER_PAGE + 3; $i++) {
            Organization::create(['name' => "Organizacja {$i}", 'description' => '']);
        }

        $this->getJson('/api/organizations?page=1')
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(OrganizationRepository::INDEX_PER_PAGE, 'items');

        $this->getJson('/api/organizations?page=2')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }

    public function test_seasons_and_tournaments_index(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon 1',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        Tournament::create([
            'name' => 'Turniej 1',
            'season_id' => $season->id,
            'date' => '2024-06-15',
        ]);

        $this->getJson('/api/seasons?page=1')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    ['id', 'title', 'subtitle', 'subtitle_missing'],
                ],
                'has_more',
                'summary',
            ])
            ->assertJsonPath('items.0.tournamentCountLabel', '1 turniej')
            ->assertJsonMissingPath('items.0.url');

        $this->getJson('/api/tournaments?page=1')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    ['id', 'title', 'subtitle', 'subtitle_missing', 'status_label', 'status_variant'],
                ],
                'has_more',
                'summary',
            ])
            ->assertJsonPath('items.0.title', 'L - Turniej 1')
            ->assertJsonMissingPath('items.0.url');
    }

    public function test_catalog_filters_and_organization_show_counts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Klub', 'description' => 'Opis']);
        $season = Season::create([
            'name' => 'Sezon A',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        Tournament::create([
            'name' => 'Na żywo',
            'season_id' => $season->id,
            'date' => '2024-01-10',
            'status' => TournamentStatus::GROUP,
        ]);
        Tournament::create([
            'name' => 'Plan',
            'season_id' => $season->id,
            'date' => '2024-12-01',
            'status' => TournamentStatus::CREATED,
        ]);
        Tournament::create([
            'name' => 'Koniec',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::FINISHED,
        ]);

        $this->getJson('/api/tournaments?status=finished')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'Klub - Koniec');

        $this->getJson('/api/organizations/'.$organization->id)
            ->assertOk()
            ->assertJsonPath('organization.tournamentCount', 3)
            ->assertJsonPath('organization.relatedUserCount', 0)
            ->assertJsonPath('organization.guestCount', 0)
            ->assertJsonPath('admins', [])
            ->assertJsonPath('relatedUsers', [])
            ->assertJsonPath('guests', [])
            ->assertJsonPath('seasons.0.tournamentCountLabel', '3 turnieje');

        $this->getJson('/api/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('community.relatedUserCount', 0)
            ->assertJsonPath('community.guestCount', 0)
            ->assertJsonPath('tournaments.0.name', 'Na żywo')
            ->assertJsonPath('tournaments.1.name', 'Plan')
            ->assertJsonPath('tournaments.2.name', 'Koniec');
    }
}
