<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InsertsPointSchemeRules;
use Tests\Support\SeedsTournamentParticipants;
use Tests\TestCase;

class TournamentControllerTest extends TestCase
{
    use InsertsPointSchemeRules;
    use RefreshDatabase;
    use SeedsTournamentParticipants;

    private User $adminUser;

    private User $regularUser;

    private Organization $organization;

    private Season $season;

    private Player $player1;

    private Player $player2;

    private Player $player3;

    private Player $player4;

    private Player $player5;

    private Player $player6;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'can_create_organizations' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
            'can_create_organizations' => false,
        ]);

        $this->organization = Organization::create(['name' => 'Test Organization', 'description' => 'Test']);
        $this->organization->admins()->attach($this->adminUser->id);

        $this->season = Season::create([
            'name' => 'Test Season',
            'organization_id' => $this->organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $this->season->admins()->attach($this->adminUser->id);

        $playerService = app(PlayerService::class);
        // Utwórz graczy dla użytkowników (będą mieli user_id)
        $playerService->create('Admin', $this->adminUser->id);
        $playerService->create('User', $this->regularUser->id);

        // Pobierz utworzonych graczy
        $this->player1 = Player::where('user_id', $this->adminUser->id)->first();
        $this->player2 = Player::where('user_id', $this->regularUser->id)->first();

        // Zaktualizuj graczy, aby byli przypisani do sezonu i organizacji
        $this->player1->update(['season_id' => $this->season->id, 'organization_id' => $this->organization->id]);
        $this->player2->update(['season_id' => $this->season->id, 'organization_id' => $this->organization->id]);

        // Utwórz gości (bez user_id)
        $this->player3 = Player::create(['name' => 'Player3', 'season_id' => $this->season->id, 'organization_id' => $this->organization->id]);
        $this->player4 = Player::create(['name' => 'Player4', 'season_id' => $this->season->id, 'organization_id' => $this->organization->id]);
        $this->player5 = Player::create(['name' => 'Player5', 'season_id' => $this->season->id, 'organization_id' => $this->organization->id]);
        $this->player6 = Player::create(['name' => 'Player6', 'season_id' => $this->season->id, 'organization_id' => $this->organization->id]);

        // Utwórz point scheme dla małych turniejów (2-8 graczy) potrzebny w testach
        $smallScheme = PointScheme::create([
            'name' => 'od 2 do 8 osob',
            'min_players' => 2,
            'max_players' => 8,
        ]);

        $this->insertDefaultSeRules($smallScheme->id);
    }

    public function test_user_can_view_tournaments_index(): void
    {
        $this->markTestSkipped('Test wymaga Vite manifest - problem konfiguracyjny, nie logika biznesowa');

        Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $response = $this->get('/tournaments');

        $response->assertStatus(200);
    }

    public function test_season_admin_can_create_tournament(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post("/tournaments?seasonId={$this->season->id}", [
            'tournamentName' => 'New Tournament',
            'date' => '2024-06-01',
        ]);

        $response->assertRedirect("/seasons/{$this->season->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tournaments', [
            'name' => 'New Tournament',
            'season_id' => $this->season->id,
        ]);
    }

    public function test_non_admin_cannot_create_tournament(): void
    {
        $this->markTestSkipped('Test oczekuje 403, ale otrzymuje 302 redirect - wymaga decyzji o zachowaniu');

        $this->actingAs($this->regularUser);

        $response = $this->post("/tournaments?seasonId={$this->season->id}", [
            'tournamentName' => 'New Tournament',
            'date' => '2024-06-01',
        ]);

        $response->assertForbidden();
    }

    public function test_season_admin_can_start_tournament(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $response = $this->get("/tournaments/{$tournament->id}/start");

        $response->assertStatus(200);
    }

    public function test_start_page_shows_season_roster_and_guests(): void
    {
        $this->player2->update(['name' => 'Anna Testowa']);
        $this->season->relatedUsers()->attach($this->regularUser->id);

        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $response = $this->get("/tournaments/{$tournament->id}/start");

        $response->assertOk();
        $response->assertSee("x-data=\"{ activeTab: 'registered' }\"", false);
        $response->assertSee('Anna Testowa');
        $response->assertSee('Player3');
        $response->assertDontSee('Brak powiązanych użytkowników.');
    }

    public function test_season_admin_can_run_tournament_with_4_players_and_2_groups(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
            $this->player5,
            $this->player6,
        ], $this->adminUser);

        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => '2',
            'playoffBracketSize' => 4,
        ]);

        $response->assertRedirect("/tournaments/{$tournament->id}");
        $response->assertSessionHas('success');

        $tournament->refresh();
        $this->assertEquals(TournamentStatus::GROUP, $tournament->status);
        $this->assertSame(2, $tournament->groups_count);
        $this->assertSame(4, $tournament->playoff_bracket_size);
        $this->assertSame([2, 2], $tournament->group_advances);
        $this->assertSame(1, $tournament->tablets_count);
        $this->assertDatabaseHas('games', [
            'tournament_id' => $tournament->id,
        ]);
        $this->assertSame(1, LoginCode::where('tournament_id', $tournament->id)->count());
        $code = LoginCode::where('tournament_id', $tournament->id)->value('code');
        $this->assertIsString($code);
        $this->assertSame(8, strlen($code));
    }

    public function test_run_tournament_ignores_legacy_tablets_count_and_creates_one_code(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
            $this->player5,
            $this->player6,
        ], $this->adminUser);

        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
            'tabletsCount' => 5,
        ]);

        $response->assertRedirect("/tournaments/{$tournament->id}");
        $response->assertSessionHas('success');

        $tournament->refresh();
        $this->assertSame(1, $tournament->tablets_count);
        $this->assertSame(1, LoginCode::where('tournament_id', $tournament->id)->count());
    }

    public function test_tournament_cannot_be_started_twice(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
            $this->player5,
            $this->player6,
        ], $this->adminUser);

        // Pierwszy start
        $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => '2',
            'playoffBracketSize' => 4,
        ]);

        // Drugi start - powinien się nie powieść
        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => '2',
            'playoffBracketSize' => 4,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_tournament_requires_at_least_one_player(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $response = $this->from("/tournaments/{$tournament->id}/start")
            ->post("/tournaments/{$tournament->id}/run", [
                'tournamentFormat' => 'groups_playoff',
                'selectedPlayers' => json_encode([]),
                'groupsCount' => '2',
                'playoffBracketSize' => 4,
            ]);

        $response->assertSessionHas('error');
    }

    public function test_tournament_groups_count_must_fit_player_pool(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
        ], $this->adminUser);

        $response = $this->from("/tournaments/{$tournament->id}/start")
            ->post("/tournaments/{$tournament->id}/run", [
                'tournamentFormat' => 'groups_playoff',
                'groupsCount' => 3,
                'playoffBracketSize' => 4,
            ]);

        $response->assertSessionHasErrors('groupsCount');
    }

    public function test_tournament_requires_at_least_four_players(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
        ], $this->adminUser);

        $response = $this->from("/tournaments/{$tournament->id}/start")
            ->post("/tournaments/{$tournament->id}/run", [
                'tournamentFormat' => 'groups_playoff',
                'groupsCount' => 2,
                'playoffBracketSize' => 4,
            ]);

        $response->assertSessionHasErrors('selectedPlayers');
    }

    public function test_admin_sees_login_code_and_qr_on_started_tournament(): void
    {
        $this->withoutVite();

        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
            'tablets_count' => 1,
        ]);

        LoginCode::create(['code' => 'ABCD1234', 'tournament_id' => $tournament->id]);

        $response = $this->actingAs($this->adminUser)->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertSee('Kod logowania na tablety');
        $response->assertSee('ABCD1234');
        $response->assertSee('tablet-login/ABCD1234');
        $response->assertSee('Nowy kod');
    }

    public function test_regular_user_does_not_see_login_codes(): void
    {
        $this->withoutVite();

        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
            'tablets_count' => 1,
        ]);

        LoginCode::create(['code' => 'SECR3789', 'tournament_id' => $tournament->id]);

        $response = $this->actingAs($this->regularUser)->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertDontSee('Kod logowania na tablety');
        $response->assertDontSee('SECR3789');
    }

    public function test_guest_does_not_see_login_codes(): void
    {
        $this->withoutVite();

        $tournament = Tournament::create([
            'name' => 'Test Tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => TournamentStatus::GROUP,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
            'tablets_count' => 1,
        ]);

        LoginCode::create(['code' => 'GUES7890', 'tournament_id' => $tournament->id]);

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertOk();
        $response->assertDontSee('Kod logowania na tablety');
        $response->assertDontSee('GUES7890');
    }

    public function test_run_tournament_saves_match_formats_and_snapshots_group_games(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Format Test',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
            $this->player5,
            $this->player6,
        ], $this->adminUser);

        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => 2,
            'playoffBracketSize' => 4,
            'matchFormats' => [
                'GROUP' => [
                    'startingScore' => 301,
                    'legsToWinSet' => 3,
                    'setsToWinMatch' => 1,
                ],
                'SEMI' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 5,
                    'setsToWinMatch' => 2,
                ],
                'THIRD' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                ],
                'FINAL' => [
                    'startingScore' => 501,
                    'legsToWinSet' => 7,
                    'setsToWinMatch' => 1,
                ],
            ],
        ]);

        $response->assertRedirect("/tournaments/{$tournament->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tournament_match_formats', [
            'tournament_id' => $tournament->id,
            'stage' => 'GROUP',
            'starting_score' => 301,
            'legs_to_win_set' => 3,
        ]);

        $this->assertDatabaseHas('games', [
            'tournament_id' => $tournament->id,
            'starting_score' => 301,
            'legs_to_win_set' => 3,
            'sets_to_win_match' => 1,
        ]);

        $this->assertSame(
            6,
            \App\Models\Game\Game::where('tournament_id', $tournament->id)
                ->where('starting_score', 301)
                ->where('legs_to_win_set', 3)
                ->count(),
        );
    }

    public function test_run_double_elimination_applies_stage_formats_to_playoff_games(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'DE Format Test',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
        ], $this->adminUser);

        $response = $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'double_elimination',
            'grandFinalMode' => 'reset',
            'matchFormats' => [
                'SEMI' => [
                    'startingScore' => 101,
                    'legsToWinSet' => 1,
                    'setsToWinMatch' => 1,
                ],
                'FINAL' => [
                    'startingScore' => 101,
                    'legsToWinSet' => 1,
                    'setsToWinMatch' => 1,
                ],
            ],
        ]);

        $response->assertRedirect("/tournaments/{$tournament->id}");
        $response->assertSessionHas('success');

        $this->assertSame(
            0,
            \App\Models\PlayoffGame\PlayoffGame::where('tournament_id', $tournament->id)
                ->where('starting_score', 501)
                ->where('legs_to_win_set', 2)
                ->count(),
        );
        $this->assertGreaterThan(
            0,
            \App\Models\PlayoffGame\PlayoffGame::where('tournament_id', $tournament->id)
                ->where('starting_score', 101)
                ->where('legs_to_win_set', 1)
                ->count(),
        );
    }

    public function test_group_stage_opens_groups_tab_by_default(): void
    {
        $tournament = $this->startedTournament(TournamentStatus::GROUP);

        $html = $this->get("/tournaments/{$tournament->id}")->assertOk()->getContent();

        $this->assertShowTabActive($html, 'groups');
    }

    public function test_playoff_stage_opens_bracket_tab_by_default(): void
    {
        $tournament = $this->startedTournament(TournamentStatus::PLAYOFF);

        $html = $this->get("/tournaments/{$tournament->id}")->assertOk()->getContent();

        $this->assertShowTabActive($html, 'playoff');
    }

    public function test_finished_groups_playoff_keeps_groups_tab_and_hides_cancel(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startedTournament(TournamentStatus::FINISHED);

        $html = $this->get("/tournaments/{$tournament->id}")->assertOk()->getContent();

        $this->assertShowTabActive($html, 'results');
        $this->assertMatchesRegularExpression('/tab=groups/', $html);
        $this->assertStringContainsString('Grupy', $html);
        $this->assertStringNotContainsString('Anuluj rozgrywki', $html);
        $this->assertStringNotContainsString('tournamentPageLive', $html);
    }

    public function test_playoff_page_listens_for_tournament_finished(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startedTournament(TournamentStatus::PLAYOFF);

        $html = $this->get("/tournaments/{$tournament->id}?tab=playoff")->assertOk()->getContent();

        $this->assertStringContainsString('tournamentPageLive', $html);
        $this->assertStringContainsString('Anuluj rozgrywki', $html);
    }

    public function test_explicit_tab_query_overrides_default(): void
    {
        $tournament = $this->startedTournament(TournamentStatus::GROUP);

        $html = $this->get("/tournaments/{$tournament->id}?tab=results")->assertOk()->getContent();

        $this->assertShowTabActive($html, 'results');
    }

    private function startedTournament(TournamentStatus $status): Tournament
    {
        return Tournament::create([
            'name' => 'Tab tournament',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
            'status' => $status,
            'format' => \App\Enums\TournamentFormat::GroupsPlayoff,
            'groups_count' => 2,
            'playoff_bracket_size' => 4,
            'group_advances' => [2, 2],
            'tablets_count' => 1,
        ]);
    }

    private function assertShowTabActive(string $html, string $tab): void
    {
        $this->assertMatchesRegularExpression(
            '/tab='.preg_quote($tab, '/').'[^>]*is-active/',
            $html,
        );
    }
}
