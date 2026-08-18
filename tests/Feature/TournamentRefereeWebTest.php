<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRefereeWebTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private string $code = 'REFWEB01';

    private Player $player1;

    private Player $player2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $admin = User::factory()->create(['can_create_organizations' => true]);
        $organization = Organization::create(['name' => 'Organization', 'description' => 't']);
        $organization->admins()->attach($admin->id);
        $season = Season::create([
            'name' => 'Season',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $playerService = app(PlayerService::class);
        $playerService->create('RefP1', $admin->id);
        $this->player1 = Player::where('user_id', $admin->id)->first();
        $this->player1->update(['season_id' => $season->id, 'organization_id' => $organization->id]);
        $this->player2 = Player::create([
            'name' => 'RefP2',
            'season_id' => $season->id,
            'organization_id' => $organization->id,
        ]);

        $this->tournament = Tournament::create([
            'name' => 'Ref Tournament',
            'season_id' => $season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
            'groups_count' => 1,
            'playoff_bracket_size' => 2,
            'group_advances' => [1],
            'tablets_count' => 1,
        ]);

        LoginCode::create([
            'code' => $this->code,
            'tournament_id' => $this->tournament->id,
        ]);
    }

    public function test_referee_login_page_renders(): void
    {
        $this->get(route('referee.login'))
            ->assertOk()
            ->assertSee('Sędziowanie w przeglądarce', false)
            ->assertSee('Kod', false)
            ->assertSee('href="'.route('pages.home').'"', false);
    }

    public function test_referee_games_and_score_pages_render(): void
    {
        $this->get(route('referee.games'))
            ->assertOk()
            ->assertSee('Mecze turnieju', false);

        $game = Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $this->get(route('referee.score', ['type' => 'group', 'id' => $game->id]))
            ->assertOk()
            ->assertSee('Wynik wizyty', false);

        $this->get(route('referee.score', ['type' => 'invalid', 'id' => 1]))
            ->assertNotFound();
    }

    public function test_tablet_login_landing_offers_web_referee_cta(): void
    {
        $this->get(route('tournaments.tablet-login-landing', ['code' => $this->code]))
            ->assertOk()
            ->assertSee('Sędziuj w przeglądarce', false)
            ->assertSee('/referee/login?code=REFWEB01', false)
            ->assertSee('auto=1', false);
    }

    public function test_tournament_code_login_can_list_active_games(): void
    {
        Game::create([
            'tournament_id' => $this->tournament->id,
            'player1_id' => $this->player1->id,
            'player2_id' => $this->player2->id,
            'group_number' => 1,
            'status' => GameStatus::SCHEDULED,
        ]);

        $login = $this->postJson('/api/login', ['code' => $this->code]);
        $login->assertOk()
            ->assertJsonPath('tournamentId', $this->tournament->id)
            ->assertJsonStructure(['token', 'tournamentId']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/game/active?tournamentId='.$this->tournament->id)
            ->assertOk()
            ->assertJsonFragment(['type' => 'group']);
    }
}
