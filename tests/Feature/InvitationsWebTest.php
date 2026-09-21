<?php

namespace Tests\Feature;

use App\Enums\LeagueGameStatus;
use App\Enums\OrganizationInvitationStatus;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\League\LeagueGame;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationInvitation;
use App\Models\Player\Player;
use App\Models\QuickGame\QuickGameLobbyInvitation;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Services\Friends\FriendshipService;
use App\Services\League\LeagueSeasonService;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use App\Services\QuickGame\QuickGameLobbyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationsWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $invitee;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['can_create_organizations' => true]);
        $this->invitee = User::factory()->create();

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->admin->id);
        $playerService->create('Invitee', $this->invitee->id);

        $this->organization = Organization::create(['name' => 'Klub Test', 'description' => '']);
        $this->organization->admins()->attach($this->admin->id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('invitations.index'))->assertRedirect('/login');
    }

    public function test_index_lists_game_invitations_and_friend_tab(): void
    {
        $tournament = $this->createTournament();
        TournamentInvitation::query()->create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => TournamentInvitationStatus::PENDING,
        ]);
        OrganizationInvitation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);
        app(FriendshipService::class)->sendInvitation($this->admin->id, $this->invitee->id);

        $this->actingAs($this->invitee)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertSee('Zaproszenia')
            ->assertSee('Turnieje')
            ->assertSee($tournament->name)
            ->assertSee('Składy')
            ->assertSee('Klub Test')
            ->assertSee(route('invitations.index'), false);

        $this->actingAs($this->invitee)
            ->get(route('invitations.index', ['tab' => 'friends']))
            ->assertOk()
            ->assertSee('Chce dodać Cię do znajomych')
            ->assertSee('Admin')
            ->assertDontSee('Brak zaproszeń do gry');
    }

    public function test_empty_tabs_show_empty_states(): void
    {
        $this->actingAs($this->invitee)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertSee('Brak zaproszeń do gry');

        $this->actingAs($this->invitee)
            ->get(route('invitations.index', ['tab' => 'friends']))
            ->assertOk()
            ->assertSee('Brak zaproszeń')
            ->assertDontSee('Brak zaproszeń do gry');
    }

    public function test_invitee_can_accept_and_reject_tournament_invitation(): void
    {
        $pending = $this->createTournamentInvitation(TournamentInvitationStatus::PENDING);
        $accepted = $this->createTournamentInvitation(TournamentInvitationStatus::ACCEPTED, 'Drugi');

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.tournaments.accept', $pending))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertSame(TournamentInvitationStatus::ACCEPTED, $pending->fresh()->status);

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.tournaments.withdraw', $accepted))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertSame(TournamentInvitationStatus::WITHDRAWN, $accepted->fresh()->status);

        $rejected = $this->createTournamentInvitation(TournamentInvitationStatus::PENDING, 'Trzeci');

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.tournaments.reject', $rejected))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertSame(TournamentInvitationStatus::REJECTED, $rejected->fresh()->status);
    }

    public function test_invitee_can_accept_and_reject_organization_invitation(): void
    {
        $accepted = $this->createOrganizationInvitation();
        $rejected = OrganizationInvitation::query()->create([
            'organization_id' => Organization::create(['name' => 'Inny klub', 'description' => ''])->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.organizations.accept', $accepted))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertTrue($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(OrganizationInvitationStatus::ACCEPTED, $accepted->fresh()->status);

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.organizations.reject', $rejected))
            ->assertSessionHas('success');

        $this->assertSame(OrganizationInvitationStatus::REJECTED, $rejected->fresh()->status);
    }

    public function test_invitee_can_join_and_reject_quick_game_lobby(): void
    {
        app(FriendshipService::class)->addFriend($this->admin->id, $this->invitee->id);
        $inviteePlayer = Player::query()->where('user_id', $this->invitee->id)->firstOrFail();
        $lobbyService = app(QuickGameLobbyService::class);

        $rejectLobby = $lobbyService->create($this->admin->id);
        $lobbyService->invite($rejectLobby->id, $this->admin->id, $inviteePlayer->id);
        $invitation = QuickGameLobbyInvitation::query()
            ->where('lobby_id', $rejectLobby->id)
            ->where('invited_player_id', $inviteePlayer->id)
            ->firstOrFail();

        $this->actingAs($this->invitee)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertSee('Admin zaprasza')
            ->assertSee('Dołącz');

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.quick-game.reject', $invitation->id))
            ->assertSessionHas('success');

        $this->assertSame('rejected', $invitation->fresh()->status);

        $rejectLobby->update(['status' => 'finished']);

        $joinLobby = $lobbyService->create($this->admin->id);
        $lobbyService->invite($joinLobby->id, $this->admin->id, $inviteePlayer->id);

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.quick-game.join', $joinLobby->id))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('quick_game_lobby_players', [
            'lobby_id' => $joinLobby->id,
            'player_id' => $inviteePlayer->id,
        ]);
    }

    public function test_invitee_can_accept_and_reject_league_game(): void
    {
        [$acceptGame, $rejectGame] = $this->createLeagueLobbies();

        $this->actingAs($this->invitee)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertSee('Do gry')
            ->assertSee('Mini');

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.league-games.accept', $acceptGame))
            ->assertRedirect(route('invitations.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($acceptGame->fresh()->opponent_accepted_at);

        $this->actingAs($this->invitee)
            ->from(route('invitations.index'))
            ->post(route('invitations.league-games.reject', $rejectGame))
            ->assertSessionHas('success');

        $rejected = $rejectGame->fresh();
        $this->assertSame(LeagueGameStatus::SCHEDULED, $rejected->status);
        $this->assertNull($rejected->lobby_host_player_id);
    }

    private function createTournament(string $name = 'Open Cup'): Tournament
    {
        $this->seedPointSchemes();

        $season = Season::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sezon '.$name,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        return Tournament::create([
            'season_id' => $season->id,
            'name' => $name,
            'date' => now(),
            'status' => TournamentStatus::CREATED,
        ]);
    }

    private function createTournamentInvitation(TournamentInvitationStatus $status, string $name = 'Open Cup'): TournamentInvitation
    {
        return TournamentInvitation::query()->create([
            'tournament_id' => $this->createTournament($name)->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => $status,
        ]);
    }

    private function createOrganizationInvitation(): OrganizationInvitation
    {
        return OrganizationInvitation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);
    }

    /**
     * @return array{0: LeagueGame, 1: LeagueGame}
     */
    private function createLeagueLobbies(): array
    {
        $leagueService = app(LeagueService::class);
        $league = $leagueService->create($this->organization->id, 'Mini', null, [[
            'name' => 'Jedyna',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);

        $host = Player::query()->where('user_id', $this->admin->id)->firstOrFail();
        $invitee = Player::query()->where('user_id', $this->invitee->id)->firstOrFail();
        $league->relatedUsers()->syncWithoutDetaching([$host->user_id, $invitee->user_id]);
        $division = $league->divisions->first();
        $leagueService->assignPlayer($league->id, $division->id, $host->id);
        $leagueService->assignPlayer($league->id, $division->id, $invitee->id);

        $season = app(LeagueSeasonService::class)->create(
            $league->id,
            'S',
            'deadline',
            2,
            '2026-09-01',
            '2026-10-01',
            null,
            true,
        );

        $games = $season->games()->orderBy('id')->get();
        $this->assertCount(2, $games);

        foreach ($games as $game) {
            $hostId = (int) $game->player1_id === $invitee->id ? $game->player2_id : $game->player1_id;
            $game->update([
                'status' => LeagueGameStatus::LOBBY,
                'lobby_host_player_id' => $hostId,
                'opponent_accepted_at' => null,
            ]);
        }

        return [$games[0]->fresh(), $games[1]->fresh()];
    }

    private function seedPointSchemes(): void
    {
        if (! \Illuminate\Support\Facades\DB::table('point_schemes')->exists()) {
            $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);
        }
    }
}
