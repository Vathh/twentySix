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

class PlayerHeadToHeadTest extends TestCase
{
    use RefreshDatabase;

    private User $annaUser;

    private User $bartekUser;

    private Player $anna;

    private Player $bartek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $players = app(PlayerService::class);
        $this->annaUser = User::factory()->create();
        $players->create('Anna', $this->annaUser->id);
        $this->anna = Player::where('user_id', $this->annaUser->id)->firstOrFail();

        $this->bartekUser = User::factory()->create();
        $players->create('Bartek', $this->bartekUser->id);
        $this->bartek = Player::where('user_id', $this->bartekUser->id)->firstOrFail();
    }

    public function test_profile_balance_matches_the_game_and_keeps_every_meeting_on_the_list(): void
    {
        Sanctum::actingAs($this->annaUser);

        $oldest = null;
        $second = null;
        $latest = null;
        foreach ([
            ['2024-01-10 18:00:00', $this->anna, 2, 0],
            ['2024-02-10 18:00:00', $this->bartek, 0, 2],
            ['2024-03-10 18:00:00', $this->anna, 2, 0],
            ['2024-04-10 18:00:00', $this->bartek, 0, 2],
            ['2024-05-10 18:00:00', $this->anna, 2, 1],
            ['2024-06-10 18:00:00', $this->anna, 2, 0],
        ] as $index => [$playedAt, $winner, $homeScore, $awayScore]) {
            $game = $this->quickGame($winner, $playedAt, $homeScore, $awayScore);
            if ($index === 0) {
                $oldest = $game;
            }
            if ($index === 4) {
                $second = $game;
            }
            $latest = $game;
        }

        $this->getJson('/api/games/quick/'.$latest->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 6)
            ->assertJsonPath('headToHead.player1Wins', 4)
            ->assertJsonPath('headToHead.player2Wins', 2)
            ->assertJsonCount(5, 'headToHead.recent')
            ->assertJsonPath('headToHead.recent.0.id', $second->id);

        $this->getJson('/api/players/'.$this->bartek->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 6)
            ->assertJsonPath('headToHead.player1Wins', 4)
            ->assertJsonPath('headToHead.player2Wins', 2)
            ->assertJsonPath('headToHead.recentHasMore', true)
            ->assertJsonCount(5, 'headToHead.recent')
            ->assertJsonPath('headToHead.recent.0.id', $latest->id)
            ->assertJsonPath('headToHead.recent.0.player1Score', 2)
            ->assertJsonPath('headToHead.recent.0.player2Score', 0)
            ->assertJsonPath('headToHead.recent.0.winnerSide', 'player1')
            ->assertJsonPath('headToHead.recent.0.placeLabel', 'Szybka gra');

        $this->getJson('/api/players/'.$this->bartek->id.'/head-to-head?offset=5')
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'recent')
            ->assertJsonPath('recent.0.id', $oldest->id);

        $this->actingAs($this->annaUser)
            ->get(route('players.show', $this->bartek))
            ->assertOk()
            ->assertSee('H2H')
            ->assertSee('Załaduj więcej')
            ->assertSee('Szybka gra')
            ->assertDontSee('Nie rozegraliście jeszcze wspólnego meczu.')
            ->assertDontSee('Brak wcześniejszych spotkań poza tym meczem.');
    }

    public function test_empty_pair_still_shows_the_tab_and_own_or_guest_profiles_do_not(): void
    {
        Sanctum::actingAs($this->annaUser);

        $this->getJson('/api/players/'.$this->bartek->id)
            ->assertOk()
            ->assertJsonPath('headToHead.meetings', 0)
            ->assertJsonCount(0, 'headToHead.recent');

        $this->getJson('/api/players/'.$this->anna->id)
            ->assertOk()
            ->assertJsonPath('headToHead', null);

        $this->actingAs($this->annaUser)
            ->get(route('players.show', $this->bartek))
            ->assertOk()
            ->assertSee('H2H')
            ->assertSee('Nie rozegraliście jeszcze wspólnego meczu.')
            ->assertDontSee('Pierwsze spotkanie tych zawodników.');

        $this->actingAs($this->annaUser)
            ->get(route('players.show', $this->anna))
            ->assertOk()
            ->assertDontSee('H2H');
    }

    public function test_guest_profile_has_no_head_to_head_tab(): void
    {
        $this->get(route('players.show', $this->bartek))
            ->assertOk()
            ->assertDontSee('H2H');
    }

    public function test_head_to_head_page_requires_the_viewer_and_rejects_their_own_profile(): void
    {
        $this->getJson('/api/players/'.$this->bartek->id.'/head-to-head')
            ->assertUnauthorized();

        $this->get(route('players.head-to-head', $this->bartek))
            ->assertRedirect(route('pages.loginPanel'));

        Sanctum::actingAs($this->annaUser);
        $this->getJson('/api/players/'.$this->anna->id.'/head-to-head')
            ->assertNotFound();

        $this->actingAs($this->annaUser)
            ->getJson(route('players.head-to-head', $this->anna))
            ->assertNotFound();

        $bare = User::factory()->create();
        Sanctum::actingAs($bare);
        $this->getJson('/api/players/'.$this->bartek->id.'/head-to-head')
            ->assertNotFound();
    }

    private function quickGame(Player $winner, string $playedAt, int $homeScore, int $awayScore): QuickGame
    {
        $game = QuickGame::create([
            'player1_id' => $this->anna->id,
            'player2_id' => $this->bartek->id,
            'player1_score' => $homeScore,
            'player2_score' => $awayScore,
            'winner_id' => $winner->id,
            'status' => GameStatus::FINISHED,
        ]);
        QuickGame::query()->whereKey($game->id)->update([
            'updated_at' => $playedAt,
            'created_at' => $playedAt,
        ]);

        return $game->fresh();
    }
}
