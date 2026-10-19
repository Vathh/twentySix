<?php

namespace Tests\Feature;

use App\Domain\RelatedRosterInvite;
use App\Enums\TournamentInvitationStatus;
use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationLeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_leave_only_themselves(): void
    {
        $member = $this->user('Członek');
        $other = $this->user('Inny');
        $organization = Organization::create(['name' => 'Warszawa', 'description' => '']);
        $organization->relatedUsers()->attach([$member->id, $other->id]);

        $league = League::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Liga',
            'description' => null,
        ]);
        $league->relatedUsers()->attach($member->id);

        $season = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Sezon',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $tournament = Tournament::create([
            'season_id' => $season->id,
            'name' => 'Open',
            'date' => now()->toDateString(),
        ]);
        TournamentInvitation::create([
            'tournament_id' => $tournament->id,
            'user_id' => $member->id,
            'invited_by' => $member->id,
            'status' => TournamentInvitationStatus::ACCEPTED,
        ]);

        $this->withoutVite();
        $this->actingAs($member)
            ->get(route('me.index'))
            ->assertOk()
            ->assertSee('Wypisz się');

        $this->actingAs($member)
            ->from(route('me.index'))
            ->post(route('me.organizations.leave', $organization))
            ->assertRedirect(route('me.index'))
            ->assertSessionHas('success');

        $fresh = $organization->fresh();
        $this->assertFalse($fresh->relatedUsers->contains('id', $member->id));
        $this->assertTrue($fresh->relatedUsers->contains('id', $other->id));
        $this->assertTrue($league->fresh()->relatedUsers->contains('id', $member->id));
        $this->assertDatabaseHas('tournament_invitations', [
            'tournament_id' => $tournament->id,
            'user_id' => $member->id,
            'status' => TournamentInvitationStatus::ACCEPTED->value,
        ]);
    }

    public function test_admin_cannot_leave_and_stranger_cannot_remove_a_member(): void
    {
        $admin = $this->user('Admin');
        $member = $this->user('Członek');
        $stranger = $this->user('Obcy');
        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->attach([$admin->id, $member->id]);

        $this->actingAs($admin)
            ->from(route('me.index'))
            ->post(route('me.organizations.leave', $organization))
            ->assertRedirect(route('me.index'))
            ->assertSessionHas('error', RelatedRosterInvite::ADMIN_MEMBERSHIP_MESSAGE);

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $admin->id));

        Sanctum::actingAs($stranger);
        $this->postJson('/api/organizations/'.$organization->id.'/leave')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Nie jesteś w składzie tej organizacji.');

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $member->id));

        Sanctum::actingAs($member);
        $this->postJson('/api/organizations/'.$organization->id.'/leave')
            ->assertOk();

        $this->assertFalse($organization->fresh()->relatedUsers->contains('id', $member->id));
        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $admin->id));
    }

    private function user(string $name): User
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create($name, $user->id);

        return $user;
    }
}
