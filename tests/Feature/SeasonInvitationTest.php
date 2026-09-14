<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Enums\OrganizationInvitationStatus;
use App\Jobs\SendInvitationPushJob;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Season\SeasonInvitation;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeasonInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $invitee;

    private Season $season;

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

        $this->season = Season::create([
            'name' => 'Sezon Test',
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $this->season->admins()->attach($this->admin->id);
    }

    public function test_send_dispatches_push_and_does_not_attach_until_accept(): void
    {
        Queue::fake();

        $this->actingAs($this->admin);

        $this->post("/seasons/{$this->season->id}/relatedUsers/add", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/seasons/{$this->season->id}/relatedUsers");

        $invitation = SeasonInvitation::query()->firstOrFail();

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($invitation) {
            return $job->recipientUserId === $this->invitee->id
                && $job->type === InvitationPushType::Season->value
                && $job->invitationId === $invitation->id
                && ($job->context['seasonName'] ?? null) === 'Sezon Test';
        });

        $this->assertFalse($this->season->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_invitee_can_list_accept_and_join_related_users(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->getJson('/api/seasons/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations.0.id', $invitation->id)
            ->assertJsonPath('invitations.0.seasonName', 'Sezon Test')
            ->assertJsonPath('invitations.0.type', 'season');

        $this->postJson("/api/seasons/invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertTrue($this->season->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::ACCEPTED,
            $invitation->fresh()->status,
        );
        $this->getJson('/api/seasons/invitations/received')
            ->assertOk()
            ->assertJsonPath('invitations', []);
    }

    public function test_invitee_can_reject_without_joining(): void
    {
        $invitation = $this->createPendingInvitation();

        Sanctum::actingAs($this->invitee);

        $this->postJson("/api/seasons/invitations/{$invitation->id}/reject")
            ->assertOk();

        $this->assertFalse($this->season->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REJECTED,
            $invitation->fresh()->status,
        );
    }

    public function test_admin_can_cancel_pending_invitation(): void
    {
        $invitation = $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post("/seasons/{$this->season->id}/relatedUsers/invitations/{$invitation->id}/cancel")
            ->assertRedirect("/seasons/{$this->season->id}/relatedUsers");

        $this->assertSame(
            OrganizationInvitationStatus::CANCELLED,
            $invitation->fresh()->status,
        );
        $this->assertFalse($this->season->fresh()->relatedUsers->contains('id', $this->invitee->id));
    }

    public function test_cannot_send_second_pending_invitation(): void
    {
        Queue::fake();
        $this->createPendingInvitation();

        $this->actingAs($this->admin);

        $this->post("/seasons/{$this->season->id}/relatedUsers/add", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/seasons/{$this->season->id}/relatedUsers")
            ->assertSessionHas('error');

        $this->assertSame(1, SeasonInvitation::query()->count());
    }

    public function test_remove_related_user_marks_invitation_removed(): void
    {
        $invitation = $this->createPendingInvitation();
        Sanctum::actingAs($this->invitee);
        $this->postJson("/api/seasons/invitations/{$invitation->id}/accept")->assertOk();

        $this->actingAs($this->admin);
        $this->delete("/seasons/{$this->season->id}/relatedUsers/remove", [
            'user_id' => $this->invitee->id,
        ])->assertRedirect("/seasons/{$this->season->id}/relatedUsers");

        $this->assertFalse($this->season->fresh()->relatedUsers->contains('id', $this->invitee->id));
        $this->assertSame(
            OrganizationInvitationStatus::REMOVED,
            $invitation->fresh()->status,
        );
    }

    private function createPendingInvitation(): SeasonInvitation
    {
        return SeasonInvitation::query()->create([
            'season_id' => $this->season->id,
            'user_id' => $this->invitee->id,
            'invited_by' => $this->admin->id,
            'status' => OrganizationInvitationStatus::PENDING,
        ]);
    }
}
