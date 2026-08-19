<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Enums\OrganizationInvitationStatus;
use App\Jobs\SendInvitationPushJob;
use App\Models\Organization\Organization;
use App\Models\Organization\OrganizationInvitation;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
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

    public function test_send_dispatches_push_and_does_not_attach_until_accept(): void
    {
        Queue::fake();

        $this->actingAs($this->admin);

        $this->post("/organizations/{$this->organization->id}/relatedUsers/add", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/organizations/{$this->organization->id}/relatedUsers");

        $invitation = OrganizationInvitation::query()->firstOrFail();

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($invitation) {
            return $job->recipientUserId === $this->invitee->id
                && $job->type === InvitationPushType::Organization->value
                && $job->invitationId === $invitation->id
                && ($job->context['organizationName'] ?? null) === 'Klub Test';
        });

        $this->assertFalse($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_invitee_can_list_accept_and_join_related_users(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->getJson('/api/organizations/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations.0.id', $invitation->id)
            ->assertJsonPath('invitations.0.organizationName', 'Klub Test')
            ->assertJsonPath('invitations.0.type', 'organization');

        $this->postJson("/api/organizations/invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertTrue($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::ACCEPTED,
            $invitation->fresh()->status,
        );
        $this->getJson('/api/organizations/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations', []);
    }

    public function test_invitee_can_reject_without_joining(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->postJson("/api/organizations/invitations/{$invitation->id}/reject")
            ->assertOk();

        $this->assertFalse($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REJECTED,
            $invitation->fresh()->status,
        );
    }

    public function test_admin_can_cancel_pending_invitation(): void
    {
        $invitation = $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post("/organizations/{$this->organization->id}/relatedUsers/invitations/{$invitation->id}/cancel")
            ->assertRedirect("/organizations/{$this->organization->id}/relatedUsers");

        $this->assertSame(
            OrganizationInvitationStatus::CANCELLED,
            $invitation->fresh()->status,
        );
        $this->assertFalse($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_cannot_send_second_pending_invitation(): void
    {
        Queue::fake();
        $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post("/organizations/{$this->organization->id}/relatedUsers/add", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/organizations/{$this->organization->id}/relatedUsers")
            ->assertSessionHas('error');

        $this->assertSame(1, OrganizationInvitation::query()->count());
    }

    public function test_remove_related_user_marks_invitation_removed(): void
    {
        $invitation = $this->createPendingInvitation();
        Sanctum::actingAs($this->invitee);
        $this->postJson("/api/organizations/invitations/{$invitation->id}/accept")->assertOk();

        $this->actingAs($this->admin);
        $this->delete("/organizations/{$this->organization->id}/relatedUsers/remove", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/organizations/{$this->organization->id}/relatedUsers");

        $this->assertFalse($this->organization->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REMOVED,
            $invitation->fresh()->status,
        );
    }

    private function createPendingInvitation(): OrganizationInvitation
    {
        return OrganizationInvitation::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);
    }
}
