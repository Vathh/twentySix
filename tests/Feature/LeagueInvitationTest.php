<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Enums\OrganizationInvitationStatus;
use App\Jobs\SendInvitationPushJob;
use App\Models\League\League;
use App\Models\League\LeagueInvitation;
use App\Models\Organization\Organization;
use App\Models\Users\User;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $invitee;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['can_create_organizations' => true]);
        $this->invitee = User::factory()->create();

        $playerService = app(PlayerService::class);
        $playerService->create('Admin', $this->admin->id);
        $playerService->create('Invitee', $this->invitee->id);

        $organization = Organization::create(['name' => 'Klub Test', 'description' => '']);
        $organization->admins()->attach($this->admin->id);

        $this->league = app(LeagueService::class)->create($organization->id, 'Pucharowa', null, [[
            'name' => 'Ekstraklasa',
            'capacity' => 8,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
    }

    public function test_send_dispatches_push_and_does_not_attach_until_accept(): void
    {
        Queue::fake();

        $this->actingAs($this->admin);

        $this->post(route('leagues.relatedUsers.add', $this->league), [
            'user_id' => $this->invitee->id,
        ])->assertRedirect(route('leagues.relatedUsers', $this->league));

        $invitation = LeagueInvitation::query()->firstOrFail();

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($invitation) {
            return $job->recipientUserId === $this->invitee->id
                && $job->type === InvitationPushType::LeagueMembership->value
                && $job->invitationId === $invitation->id
                && ($job->context['leagueName'] ?? null) === 'Pucharowa';
        });

        $this->assertFalse($this->league->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_invitee_can_list_accept_and_join_related_users(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->getJson('/api/leagues/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations.0.id', $invitation->id)
            ->assertJsonPath('invitations.0.leagueName', 'Pucharowa')
            ->assertJsonPath('invitations.0.type', 'league_membership');

        $this->postJson("/api/leagues/invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertTrue($this->league->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::ACCEPTED,
            $invitation->fresh()->status,
        );
        $this->getJson('/api/leagues/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations', []);
    }

    public function test_invitee_can_reject_without_joining(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->postJson("/api/leagues/invitations/{$invitation->id}/reject")
            ->assertOk();

        $this->assertFalse($this->league->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REJECTED,
            $invitation->fresh()->status,
        );
    }

    public function test_admin_can_cancel_pending_invitation(): void
    {
        $invitation = $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post(route('leagues.relatedUsers.invitations.cancel', [$this->league, $invitation->id]))
            ->assertRedirect(route('leagues.relatedUsers', $this->league));

        $this->assertSame(
            OrganizationInvitationStatus::CANCELLED,
            $invitation->fresh()->status,
        );
        $this->assertFalse($this->league->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_cannot_send_second_pending_invitation(): void
    {
        Queue::fake();
        $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post(route('leagues.relatedUsers.add', $this->league), [
            'user_id' => $this->invitee->id,
        ])->assertRedirect(route('leagues.relatedUsers', $this->league))
            ->assertSessionHas('error');

        $this->assertSame(1, LeagueInvitation::query()->count());
    }

    public function test_remove_related_user_marks_invitation_removed(): void
    {
        $invitation = $this->createPendingInvitation();
        Sanctum::actingAs($this->invitee);
        $this->postJson("/api/leagues/invitations/{$invitation->id}/accept")->assertOk();

        $this->actingAs($this->admin);
        $this->delete(route('leagues.relatedUsers.remove', $this->league), [
            'user_id' => $this->invitee->id,
        ])->assertRedirect(route('leagues.relatedUsers', $this->league));

        $this->assertFalse($this->league->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REMOVED,
            $invitation->fresh()->status,
        );
    }

    private function createPendingInvitation(): LeagueInvitation
    {
        return LeagueInvitation::query()->create([
            'league_id' => $this->league->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);
    }
}
