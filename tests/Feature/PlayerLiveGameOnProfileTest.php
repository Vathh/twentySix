<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerLiveGameOnProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_link_to_live_group_game(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $playerService = app(PlayerService::class);

        $user1 = User::factory()->create();
        $playerService->create('Live Player', $user1->id);
        $player1 = Player::where('user_id', $user1->id)->firstOrFail();

        $user2 = User::factory()->create();
        $playerService->create('Opponent', $user2->id);
        $player2 = Player::where('user_id', $user2->id)->firstOrFail();

        $organization = Organization::create(['name' => 'Organizacja', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'Turniej Live',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
        ]);

        $game = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'group_number' => 1,
            'status' => GameStatus::IN_PROGRESS,
        ]);

        $response = $this->get(route('players.show', $player1));

        $response->assertOk();
        $response->assertSee('Na żywo');
        $response->assertSee('Gra teraz vs Opponent');
        $response->assertSee('Turniej Live');
        $response->assertSee(route('games.live', ['type' => 'group', 'id' => $game->id]), false);
    }

    public function test_profile_hides_live_banner_when_no_in_progress_game(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $playerService = app(PlayerService::class);
        $user = User::factory()->create();
        $playerService->create('Idle Player', $user->id);
        $player = Player::where('user_id', $user->id)->firstOrFail();

        $this->get(route('players.show', $player))
            ->assertOk()
            ->assertDontSee('Gra teraz vs');
    }
}
