<?php

namespace Tests\Feature;

use App\Enums\OrganizationInvitationStatus;
use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationInvitation;
use App\Models\Organization\OrganizationJoinRequest;
use App\Models\Player\Player;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationJoinAndInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_apply_and_admin_can_approve(): void
    {
        [$admin, $applicant, $organization] = $this->organizationWithPeople();

        $this->actingAs($applicant)
            ->post(route('organizations.join-requests.store', $organization))
            ->assertRedirect(route('organizations.show', $organization));

        $request = OrganizationJoinRequest::query()->firstOrFail();
        $this->assertSame('pending', $request->status->value);

        $this->actingAs($applicant)
            ->post(route('organizations.join-requests.store', $organization))
            ->assertRedirect(route('organizations.show', $organization));
        $this->assertSame(1, OrganizationJoinRequest::query()->count());

        $this->actingAs($admin)
            ->post(route('organizations.join-requests.approve', [$organization, $request]))
            ->assertRedirect(route('organizations.relatedUsers', $organization));

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ]);
        $this->assertSame('approved', $request->fresh()->status->value);
    }

    public function test_rejected_request_can_be_sent_again(): void
    {
        [$admin, $applicant, $organization] = $this->organizationWithPeople();
        $this->actingAs($applicant)->post(route('organizations.join-requests.store', $organization));
        $request = OrganizationJoinRequest::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('organizations.join-requests.reject', [$organization, $request]))
            ->assertRedirect();

        $this->actingAs($applicant)
            ->post(route('organizations.join-requests.store', $organization))
            ->assertRedirect();

        $this->assertSame(1, OrganizationJoinRequest::query()->count());
        $this->assertSame('pending', $request->fresh()->status->value);
    }

    public function test_pending_invitation_blocks_a_join_request(): void
    {
        [$admin, $applicant, $organization] = $this->organizationWithPeople();
        OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
            'invited_by' => $admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);

        $this->actingAs($applicant)
            ->post(route('organizations.join-requests.store', $organization))
            ->assertRedirect(route('organizations.show', $organization))
            ->assertSessionHas('error', 'Masz zaproszenie do tej organizacji.');

        $this->assertSame(0, OrganizationJoinRequest::query()->count());
    }

    public function test_stranger_cannot_approve_a_join_request(): void
    {
        [, $applicant, $organization] = $this->organizationWithPeople();
        $this->actingAs($applicant)->post(route('organizations.join-requests.store', $organization));
        $request = OrganizationJoinRequest::query()->firstOrFail();
        $stranger = User::factory()->create();
        app(PlayerService::class)->create('Obcy', $stranger->id);

        $this->actingAs($stranger)
            ->post(route('organizations.join-requests.approve', [$organization, $request]))
            ->assertForbidden();
    }

    public function test_public_page_offers_apply_or_login(): void
    {
        [, $applicant, $organization] = $this->organizationWithPeople();

        $this->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertSee('Zaloguj się, aby się zgłosić');

        $this->actingAs($applicant)
            ->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertSee('Zgłoś się');
    }

    public function test_finished_tournament_lets_org_admin_invite_a_participant(): void
    {
        Queue::fake();
        [$admin, $playerUser, $organization] = $this->organizationWithPeople();
        $guest = Player::create(['name' => 'Gość', 'user_id' => null]);
        $player = Player::where('user_id', $playerUser->id)->firstOrFail();
        $tournament = Tournament::create([
            'name' => 'Finał',
            'organization_id' => $organization->id,
            'date' => '2026-11-01',
            'status' => TournamentStatus::FINISHED,
        ]);
        TournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'place' => 1,
            'points' => 0,
        ]);
        TournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_id' => $guest->id,
            'place' => 2,
            'points' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'results']))
            ->assertOk()
            ->assertSee('Zaproś do organizacji')
            ->assertSee('Gość');

        $this->actingAs($admin)
            ->post(route('tournaments.organization-invite', $tournament), ['user_id' => $playerUser->id])
            ->assertRedirect(route('tournaments.show', ['tournament' => $tournament->id, 'tab' => 'results']));

        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'user_id' => $playerUser->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('canInviteToOrganization', true)
            ->assertJsonPath('results.0.organizationInvite', 'pending');
    }

    public function test_live_tournament_does_not_offer_an_organization_invite(): void
    {
        [$admin, $playerUser, $organization] = $this->organizationWithPeople();
        $player = Player::where('user_id', $playerUser->id)->firstOrFail();
        $tournament = Tournament::create([
            'name' => 'W toku',
            'organization_id' => $organization->id,
            'date' => '2026-11-01',
            'status' => TournamentStatus::GROUP,
        ]);
        TournamentResult::create([
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'place' => 1,
            'points' => 0,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/tournaments/'.$tournament->id)
            ->assertOk()
            ->assertJsonPath('canInviteToOrganization', false);

        $this->actingAs($admin)
            ->post(route('tournaments.organization-invite', $tournament), ['user_id' => $playerUser->id])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Organization}
     */
    private function organizationWithPeople(): array
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        $applicant = User::factory()->create();
        $players = app(PlayerService::class);
        $players->create('Admin', $admin->id);
        $players->create('Zawodnik', $applicant->id);
        $organization = Organization::create(['name' => 'Klub '.uniqid(), 'description' => '']);
        $organization->admins()->attach($admin->id);

        return [$admin, $applicant, $organization];
    }
}
