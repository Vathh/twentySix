<?php

namespace Tests\Feature;

use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_renders(): void
    {
        $this->get(route('players.search'))
            ->assertOk()
            ->assertSee('Szukaj graczy');
    }

    public function test_search_json_returns_matching_registered_player(): void
    {
        $playerService = app(PlayerService::class);
        $matched = User::factory()->create();
        $other = User::factory()->create();
        $playerService->create('Bartek Demo', $matched->id);
        $playerService->create('Ola', $other->id);

        $this->getJson(route('players.search', ['q' => 'Bartek']))
            ->assertOk()
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.name', 'Bartek Demo')
            ->assertJsonPath('players.0.initials', 'BD');
    }

    public function test_search_json_with_empty_query_returns_no_players(): void
    {
        $this->getJson(route('players.search'))
            ->assertOk()
            ->assertJsonPath('players', []);
    }
}
