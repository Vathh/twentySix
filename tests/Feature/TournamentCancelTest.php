<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\Season\Season;
use App\Models\Tournament\LoginCode;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InsertsPointSchemeRules;
use Tests\Support\SeedsTournamentParticipants;
use Tests\TestCase;

class TournamentCancelTest extends TestCase
{
    use InsertsPointSchemeRules;
    use RefreshDatabase;
    use SeedsTournamentParticipants;

    private User $adminUser;

    private User $regularUser;

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
            'email' => 'admin-cancel@test.com',
            'can_create_organizations' => true,
        ]);
        $this->regularUser = User::factory()->create([
            'email' => 'user-cancel@test.com',
            'can_create_organizations' => false,
        ]);

        $organization = Organization::create(['name' => 'Org', 'description' => '']);
        $organization->admins()->attach($this->adminUser->id);

        $this->season = Season::create([
            'name' => 'Season',
            'organization_id' => $organization->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $this->season->admins()->attach($this->adminUser->id);

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->adminUser->id);
        $playerService->create('User', $this->regularUser->id);

        $this->player1 = Player::where('user_id', $this->adminUser->id)->first();
        $this->player2 = Player::where('user_id', $this->regularUser->id)->first();
        $this->player3 = Player::create(['name' => 'G3', 'season_id' => $this->season->id]);
        $this->player4 = Player::create(['name' => 'G4', 'season_id' => $this->season->id]);
        $this->player5 = Player::create(['name' => 'G5', 'season_id' => $this->season->id]);
        $this->player6 = Player::create(['name' => 'G6', 'season_id' => $this->season->id]);

        $scheme = PointScheme::create([
            'name' => 'od 2 do 8 osob',
            'min_players' => 2,
            'max_players' => 8,
        ]);
        $this->insertDefaultSeRules($scheme->id);
    }

    public function test_admin_can_cancel_started_tournament_with_password(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startGroupTournament();

        $this->assertSame(TournamentStatus::GROUP, $tournament->fresh()->status);
        $this->assertDatabaseHas('games', ['tournament_id' => $tournament->id]);
        $invitationCount = TournamentInvitation::where('tournament_id', $tournament->id)->count();
        $this->assertGreaterThan(0, $invitationCount);

        $response = $this->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.cancel', $tournament), [
                'current_password' => 'password',
            ]);

        $response->assertRedirect(route('tournaments.start', $tournament));
        $response->assertSessionHas('success');

        $tournament->refresh();
        $this->assertSame(TournamentStatus::CREATED, $tournament->status);
        $this->assertNull($tournament->groups_count);
        $this->assertNull($tournament->playoff_bracket_size);
        $this->assertDatabaseMissing('games', ['tournament_id' => $tournament->id]);
        $this->assertDatabaseMissing('group_standings', ['tournament_id' => $tournament->id]);
        $this->assertSame(0, LoginCode::where('tournament_id', $tournament->id)->count());
        $this->assertSame(
            $invitationCount,
            TournamentInvitation::where('tournament_id', $tournament->id)->count(),
        );
    }

    public function test_cancel_rejects_wrong_password(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startGroupTournament();

        $this->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.cancel', $tournament), [
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('tournaments.show', $tournament))
            ->assertSessionHasErrors('current_password');

        $this->assertSame(TournamentStatus::GROUP, $tournament->fresh()->status);
        $this->assertDatabaseHas('games', ['tournament_id' => $tournament->id]);
    }

    public function test_non_admin_cannot_cancel_tournament(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startGroupTournament();

        $this->actingAs($this->regularUser)
            ->post(route('tournaments.cancel', $tournament), [
                'current_password' => 'password',
            ])
            ->assertForbidden();

        $this->assertSame(TournamentStatus::GROUP, $tournament->fresh()->status);
    }

    public function test_cannot_cancel_unstarted_tournament(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = Tournament::create([
            'name' => 'Not started',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);
        $tournament->admins()->attach($this->adminUser->id);

        $this->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.cancel', $tournament), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('tournaments.show', $tournament))
            ->assertSessionHasErrors('tournament');
    }

    public function test_cannot_cancel_finished_tournament(): void
    {
        $this->actingAs($this->adminUser);
        $tournament = $this->startGroupTournament();
        $tournament->update(['status' => TournamentStatus::FINISHED]);

        $this->from(route('tournaments.show', $tournament))
            ->post(route('tournaments.cancel', $tournament), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('tournaments.show', $tournament))
            ->assertSessionHasErrors('tournament');

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertDontSee('Anuluj rozgrywki');

        $this->assertSame(TournamentStatus::FINISHED, $tournament->fresh()->status);
        $this->assertDatabaseHas('games', ['tournament_id' => $tournament->id]);
    }

    private function startGroupTournament(): Tournament
    {
        $tournament = Tournament::create([
            'name' => 'Live event',
            'season_id' => $this->season->id,
            'date' => '2024-06-01',
        ]);
        $tournament->admins()->attach($this->adminUser->id);

        $this->addPlayersToTournamentPool($tournament, [
            $this->player1,
            $this->player2,
            $this->player3,
            $this->player4,
            $this->player5,
            $this->player6,
        ], $this->adminUser);

        $this->post("/tournaments/{$tournament->id}/run", [
            'tournamentFormat' => 'groups_playoff',
            'groupsCount' => '2',
            'playoffBracketSize' => 4,
        ])->assertRedirect("/tournaments/{$tournament->id}");

        return $tournament->fresh();
    }
}
