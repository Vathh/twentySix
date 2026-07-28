<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $profileUser;

    private Player $profilePlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $playerService = app(PlayerService::class);

        $this->viewer = User::factory()->create();
        $playerService->create('Viewer', $this->viewer->id);

        $this->profileUser = User::factory()->create();
        $playerService->create('Anna Nowak', $this->profileUser->id);
        $this->profilePlayer = Player::where('user_id', $this->profileUser->id)->firstOrFail();
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/players/'.$this->profilePlayer->id)
            ->assertUnauthorized();
    }

    public function test_registered_player_profile_returns_stats_and_friendship(): void
    {
        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$this->profilePlayer->id)
            ->assertOk()
            ->assertJsonPath('player.id', $this->profilePlayer->id)
            ->assertJsonPath('player.name', 'Anna Nowak')
            ->assertJsonStructure([
                'player' => ['id', 'userId', 'name', 'registeredAt'],
                'friendship' => [
                    'isSelf',
                    'isFriend',
                    'canInvite',
                    'pendingSent',
                    'pendingReceived',
                ],
                'quickStats' => [
                    'games',
                    'avg_three_darts',
                    'highest_hf',
                    'fastest_qf',
                    'count_max',
                    'count_170_plus',
                    'count_hf',
                    'count_qf',
                ],
                'tournamentStats' => [
                    'games',
                    'avg_three_darts',
                    'highest_hf',
                    'fastest_qf',
                    'count_max',
                    'count_170_plus',
                    'count_hf',
                    'count_qf',
                ],
                'gameHistory' => ['items', 'hasMore'],
            ])
            ->assertJsonPath('friendship.isSelf', false)
            ->assertJsonPath('friendship.canInvite', true);
    }

    public function test_guest_player_without_user_returns_404(): void
    {
        $guest = Player::create([
            'name' => 'Guest Only',
            'user_id' => null,
        ]);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$guest->id)
            ->assertNotFound();
    }

    public function test_games_history_page_endpoint(): void
    {
        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/players/'.$this->profilePlayer->id.'/games?page=1')
            ->assertOk()
            ->assertJsonStructure(['items', 'has_more']);
    }
}
