<?php

namespace Tests\Feature;

use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentJoinRequestStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentJoinRequestsUpdated;
use App\Models\League\League;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Tournament\TournamentJoinRequest;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentJoinRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $this->admin = User::factory()->create();
        $this->player = User::factory()->create();

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->admin->id);
        $playerService->create('Player One', $this->player->id);

        $league = League::create(['name' => 'Liga QR', 'description' => '']);
        $league->relatedUsers()->attach($this->admin->id);

        $season = Season::create([
            'league_id' => $league->id,
            'name' => 'S1',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);

        $this->tournament = Tournament::create([
            'season_id' => $season->id,
            'name' => 'Turniej QR',
            'date' => now(),
            'status' => TournamentStatus::CREATED,
            'join_code' => 'ABCD1234',
            'join_code_generated_at' => now(),
            'join_code_enabled' => true,
        ]);
        $this->tournament->admins()->attach($this->admin->id);
    }

    public function test_player_can_apply_via_api(): void
    {
        Event::fake([TournamentJoinRequestsUpdated::class]);

        Sanctum::actingAs($this->player);

        $this->postJson('/api/tournaments/join/ABCD1234')
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('tournament_join_requests', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $this->player->id,
            'status' => TournamentJoinRequestStatus::PENDING->value,
        ]);

        Event::assertDispatched(TournamentJoinRequestsUpdated::class, function (TournamentJoinRequestsUpdated $event) {
            return $event->tournamentId === $this->tournament->id
                && count($event->payload['requests']) === 1
                && $event->payload['requests'][0]['playerName'] === 'Player One';
        });

        // Idempotent second apply
        $this->postJson('/api/tournaments/join/ABCD1234')
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertSame(
            1,
            TournamentJoinRequest::where('tournament_id', $this->tournament->id)
                ->where('user_id', $this->player->id)
                ->where('status', TournamentJoinRequestStatus::PENDING)
                ->count()
        );

        // Second apply (already pending) must not re-broadcast
        Event::assertDispatchedTimes(TournamentJoinRequestsUpdated::class, 1);
    }

    public function test_admin_approve_adds_accepted_invitation(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')->assertOk();

        $request = TournamentJoinRequest::first();

        $this->actingAs($this->admin)
            ->postJson(route('tournaments.join-requests.approve', [$this->tournament->id, $request->id]))
            ->assertOk()
            ->assertJsonPath('message', 'Zawodnik dołączony do turnieju')
            ->assertJsonCount(0, 'requests')
            ->assertJsonPath('participantCount', 1)
            ->assertJsonPath('participants.0.name', 'Player One');

        $this->assertDatabaseHas('tournament_invitations', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $this->player->id,
            'status' => TournamentInvitationStatus::ACCEPTED->value,
        ]);

        $request->refresh();
        $this->assertSame(TournamentJoinRequestStatus::APPROVED, $request->status);
    }

    public function test_admin_reject_allows_reapply(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')->assertOk();
        $request = TournamentJoinRequest::first();

        $this->actingAs($this->admin)
            ->postJson(route('tournaments.join-requests.reject', [$this->tournament->id, $request->id]))
            ->assertOk()
            ->assertJsonPath('message', 'Zgłoszenie odrzucone')
            ->assertJsonCount(0, 'requests');

        $request->refresh();
        $this->assertSame(TournamentJoinRequestStatus::REJECTED, $request->status);

        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_apply_blocked_when_tournament_started(): void
    {
        $this->tournament->update(['status' => TournamentStatus::GROUP]);

        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')
            ->assertStatus(422);
    }

    public function test_preview_endpoint(): void
    {
        Sanctum::actingAs($this->player);

        $this->getJson('/api/tournaments/join/ABCD1234')
            ->assertOk()
            ->assertJsonPath('tournamentName', 'Turniej QR')
            ->assertJsonPath('canApply', true);
    }

    public function test_start_page_shows_join_code(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tournaments.start', $this->tournament->id))
            ->assertOk()
            ->assertSee('ABCD1234')
            ->assertSee('Dołącz przez QR')
            ->assertSee('tournamentJoinRequestsLive');
    }

    public function test_admin_can_remove_participant_via_json(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')->assertOk();
        $request = TournamentJoinRequest::first();

        $this->actingAs($this->admin)
            ->postJson(route('tournaments.join-requests.approve', [$this->tournament->id, $request->id]))
            ->assertOk();

        $invitation = TournamentInvitation::where('tournament_id', $this->tournament->id)
            ->where('user_id', $this->player->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('tournaments.invitations.remove', [$this->tournament->id, $invitation->id]))
            ->assertOk()
            ->assertJsonPath('message', 'Uczestnik usunięty z turnieju')
            ->assertJsonPath('participantCount', 0);
    }

    public function test_join_requests_live_endpoint(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/tournaments/join/ABCD1234')->assertOk();

        $this->actingAs($this->admin)
            ->getJson(route('tournaments.join-requests-live', $this->tournament->id))
            ->assertOk()
            ->assertJsonPath('tournamentId', $this->tournament->id)
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.playerName', 'Player One');
    }

    public function test_invitation_search_and_send_via_json(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('tournaments.invitations.search', $this->tournament->id).'?q=Player')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->player->id, 'name' => 'Player One']);

        $this->actingAs($this->admin)
            ->postJson(route('tournaments.invitations.send', $this->tournament->id), [
                'user_id' => $this->player->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Zaproszenie wysłane')
            ->assertJsonCount(1, 'invitationPipeline')
            ->assertJsonPath('invitationPipeline.0.name', 'Player One');

        $this->assertDatabaseHas('tournament_invitations', [
            'tournament_id' => $this->tournament->id,
            'user_id' => $this->player->id,
            'status' => TournamentInvitationStatus::PENDING->value,
        ]);
    }
}
