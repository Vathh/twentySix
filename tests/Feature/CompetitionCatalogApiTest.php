<?php

namespace Tests\Feature;

use App\Models\League\League;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\League\LeagueRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_leagues(): void
    {
        $this->getJson('/api/leagues')->assertUnauthorized();
    }

    public function test_guest_cannot_list_seasons(): void
    {
        $this->getJson('/api/seasons')->assertUnauthorized();
    }

    public function test_guest_cannot_list_tournaments(): void
    {
        $this->getJson('/api/tournaments')->assertUnauthorized();
    }

    public function test_leagues_index_returns_items_without_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        League::create(['name' => 'Liga Test', 'description' => 'Opis']);

        $this->getJson('/api/leagues?page=1')
            ->assertOk()
            ->assertJsonStructure([
                'items' => [
                    ['id', 'title', 'subtitle'],
                ],
                'has_more',
            ])
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('items.0.title', 'Liga Test')
            ->assertJsonMissingPath('items.0.url');
    }

    public function test_leagues_index_paginates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 1; $i <= LeagueRepository::INDEX_PER_PAGE + 3; $i++) {
            League::create(['name' => "Liga {$i}", 'description' => '']);
        }

        $this->getJson('/api/leagues?page=1')
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(LeagueRepository::INDEX_PER_PAGE, 'items');

        $this->getJson('/api/leagues?page=2')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }

    public function test_seasons_and_tournaments_index(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $league = League::create(['name' => 'L', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon 1',
            'league_id' => $league->id,
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
