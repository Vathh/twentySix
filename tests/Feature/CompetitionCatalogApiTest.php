<?php

namespace Tests\Feature;

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
                    ['id', 'title', 'subtitle'],
                ],
                'has_more',
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
            ])
            ->assertJsonMissingPath('items.0.url');

        $this->getJson('/api/tournaments?page=1')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    ['id', 'title', 'subtitle', 'subtitle_missing', 'status_label', 'status_variant'],
                ],
                'has_more',
            ])
			->assertJsonPath('items.0.title', 'L - Turniej 1')
            ->assertJsonMissingPath('items.0.url');
    }
}
