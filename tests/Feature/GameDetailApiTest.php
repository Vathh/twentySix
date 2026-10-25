<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGame;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameDetailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_game_detail(): void
    {
        $this->getJson('/api/games/quick/1')->assertUnauthorized();
    }

    public function test_quick_game_detail_returns_score_and_players(): void
    {
        $playerService = app(PlayerService::class);
        $viewer = User::factory()->create();
        $playerService->create('Viewer', $viewer->id);

        $homeUser = User::factory()->create();
        $awayUser = User::factory()->create();
        $playerService->create('Anna', $homeUser->id);
        $playerService->create('Bartek', $awayUser->id);
        $home = Player::where('user_id', $homeUser->id)->firstOrFail();
        $away = Player::where('user_id', $awayUser->id)->firstOrFail();

        $game = QuickGame::create([
            'player1_id' => $home->id,
            'player2_id' => $away->id,
            'player1_score' => 2,
            'player2_score' => 1,
            'winner_id' => $home->id,
            'status' => GameStatus::FINISHED,
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/games/quick/'.$game->id)
            ->assertOk()
            ->assertJsonPath('kind', 'quick')
            ->assertJsonPath('gameId', $game->id)
            ->assertJsonPath('label', 'Towarzyski')
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('player1.name', 'Anna')
            ->assertJsonPath('player2.name', 'Bartek')
            ->assertJsonPath('player1Score', 2)
            ->assertJsonPath('player2Score', 1)
            ->assertJsonPath('winnerId', $home->id)
            ->assertJsonPath('sets', []);
    }

    public function test_unknown_game_returns_404(): void
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create('Viewer', $user->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/games/quick/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Nie znaleziono meczu.');
    }
}
