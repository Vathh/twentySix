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

class IndexLoadMorePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leagues_index_returns_first_page_and_has_more(): void
    {
        $user = User::factory()->create(['can_create_leagues' => true]);
        Sanctum::actingAs($user);

        for ($i = 1; $i <= LeagueRepository::INDEX_PER_PAGE + 3; $i++) {
            League::create(['name' => "Liga {$i}", 'description' => '']);
        }

        $this->get(route('leagues.index'))
            ->assertOk()
            ->assertViewHas('hasMore', true)
            ->assertViewHas('items', fn ($items) => count($items) === LeagueRepository::INDEX_PER_PAGE);

        $this->getJson(route('leagues.index', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }

    public function test_seasons_and_tournaments_index_paginate(): void
    {
        $user = User::factory()->create(['can_create_leagues' => true]);
        Sanctum::actingAs($user);

        $league = League::create(['name' => 'L', 'description' => '']);
        for ($i = 1; $i <= 12; $i++) {
            $season = Season::create([
                'name' => "S{$i}",
                'league_id' => $league->id,
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
