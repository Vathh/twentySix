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

class IndexLoadMorePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_index_returns_first_page_and_has_more(): void
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        Sanctum::actingAs($user);

        for ($i = 1; $i <= OrganizationRepository::INDEX_PER_PAGE + 3; $i++) {
            Organization::create(['name' => "Organizacja {$i}", 'description' => '']);
        }

        $this->get(route('organizations.index'))
            ->assertOk()
            ->assertViewHas('hasMore', true)
            ->assertViewHas('items', fn ($items) => count($items) === OrganizationRepository::INDEX_PER_PAGE);

        $this->getJson(route('organizations.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }

    public function test_seasons_and_tournaments_index_paginate(): void
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        Sanctum::actingAs($user);

        $organization = Organization::create(['name' => 'L', 'description' => '']);
        for ($i = 1; $i <= 12; $i++) {
            $season = Season::create([
                'name' => "S{$i}",
                'organization_id' => $organization->id,
                'start_date' => sprintf('2024-%02d-01', min($i, 12)),
                'end_date' => '2024-12-31',
            ]);
            Tournament::create([
                'name' => "T{$i}",
                'season_id' => $season->id,
                'date' => sprintf('2024-%02d-15', min($i, 12)),
            ]);
        }

        $this->getJson(route('seasons.index', ['page' => 1]))
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(9, 'items');

        $this->getJson(route('tournaments.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }
}
