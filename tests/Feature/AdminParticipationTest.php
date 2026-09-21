<?php

namespace Tests\Feature;

use App\Domain\RelatedRosterInvite;
use App\Enums\TournamentInvitationStatus;
use App\Enums\TournamentStatus;
use App\Jobs\SendInvitationPushJob;
use App\Models\League\League;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Services\League\LeagueService;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use App\Services\Season\SeasonService;
use App\Services\Tournament\TournamentInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminParticipationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_organization_puts_admin_in_roster_and_league_pools(): void
    {
        $admin = $this->userWithPlayer('Admin Org');
        $extra = $this->userWithPlayer('Drugi Admin');

        $organization = app(OrganizationService::class)->create('Klub', 'Opis', $admin->id);

        $this->assertTrue($organization->id > 0);
        $this->assertTrue(
            Organization::query()->findOrFail($organization->id)->relatedUsers->contains('id', $admin->id),
        );

        $league = app(LeagueService::class)->create($organization->id, 'Liga', null, [[
            'name' => 'Ekstraklasa',
            'capacity' => 4,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
        ]]);

        $this->assertTrue($league->fresh()->relatedUsers->contains('id', $admin->id));

        app(OrganizationService::class)->addAdmin($organization->id, $extra->id);

        $freshLeague = $league->fresh();
        $this->assertTrue(Organization::query()->findOrFail($organization->id)->relatedUsers->contains('id', $extra->id));
        $this->assertTrue($freshLeague->relatedUsers->contains('id', $extra->id));
        $this->assertTrue(Organization::query()->findOrFail($organization->id)->admins->contains('id', $extra->id));
    }

    public function test_creating_season_puts_season_admins_in_season_roster(): void
    {
        $admin = $this->userWithPlayer('Admin Sezonu');
        $organization = Organization::query()->create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);

        app(SeasonService::class)->create($organization->id, 'Sezon 1', [], '2026-01-01', '2026-12-31');

        $season = Season::query()->where('name', 'Sezon 1')->firstOrFail();
        $this->assertTrue($season->admins->contains('id', $admin->id));
        $this->assertTrue($season->relatedUsers->contains('id', $admin->id));
    }

    public function test_removing_admin_role_keeps_roster_membership(): void
    {
        $admin = $this->userWithPlayer('Admin');
        $other = $this->userWithPlayer('Drugi');
        $organization = Organization::query()->create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);

        $service = app(OrganizationService::class);
        $service->addAdmin($organization->id, $other->id);
        $service->removeAdmin($organization->id, $other->id);

        $fresh = $organization->fresh();
        $this->assertFalse($fresh->admins->contains('id', $other->id));
        $this->assertTrue($fresh->relatedUsers->contains('id', $other->id));
    }

    public function test_admin_cannot_be_removed_from_roster_while_they_administer_it(): void
    {
        $admin = $this->userWithPlayer('Admin');
        $member = $this->userWithPlayer('Skład');
        $organization = Organization::query()->create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);
        $organization->relatedUsers()->syncWithoutDetaching([$admin->id, $member->id]);

        $this->actingAs($admin)
            ->delete("/organizations/{$organization->id}/relatedUsers/remove", ['user_id' => $admin->id])
            ->assertRedirect("/organizations/{$organization->id}/relatedUsers")
            ->assertSessionHas('error', RelatedRosterInvite::ADMIN_MEMBERSHIP_MESSAGE);

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $admin->id));

        $this->actingAs($admin)
            ->delete("/organizations/{$organization->id}/relatedUsers/remove", ['user_id' => $member->id])
            ->assertSessionHas('success');

        $this->assertFalse($organization->fresh()->relatedUsers->contains('id', $member->id));
    }

    public function test_admin_self_invite_joins_roster_without_invitation(): void
    {
        Queue::fake();

        $admin = $this->userWithPlayer('Admin Solo');
        $organization = Organization::query()->create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);

        $this->actingAs($admin)
            ->postJson("/organizations/{$organization->id}/relatedUsers/add", ['user_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('message', RelatedRosterInvite::SELF_JOIN_MESSAGE);

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $admin->id));
        $this->assertDatabaseMissing('organization_invitations', [
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_admin_already_in_roster_still_cannot_invite_self(): void
    {
        $admin = $this->userWithPlayer('Admin Jest');
        $organization = app(OrganizationService::class)->create('Klub', null, $admin->id);

        $this->actingAs($admin)
            ->post("/organizations/{$organization->id}/relatedUsers/add", ['user_id' => $admin->id])
            ->assertRedirect("/organizations/{$organization->id}/relatedUsers")
            ->assertSessionHas('error', 'Użytkownik jest już powiązany z tą organizacją');
    }

    public function test_season_and_league_admins_cannot_leave_roster_and_can_join_self(): void
    {
        $admin = $this->userWithPlayer('Admin Puli');
        $organization = Organization::query()->create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);

        $season = Season::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sezon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $season->admins()->attach($admin->id);

        $this->actingAs($admin)
            ->postJson("/seasons/{$season->id}/relatedUsers/add", ['user_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->assertTrue($season->fresh()->relatedUsers->contains('id', $admin->id));

        $this->actingAs($admin)
            ->delete("/seasons/{$season->id}/relatedUsers/remove", ['user_id' => $admin->id])
            ->assertSessionHas('error', RelatedRosterInvite::ADMIN_MEMBERSHIP_MESSAGE);

        $league = League::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Liga surowa',
            'description' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('leagues.relatedUsers.add', $league), ['user_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id);

        $this->assertTrue($league->fresh()->relatedUsers->contains('id', $admin->id));
        $this->assertDatabaseMissing('league_invitations', [
            'league_id' => $league->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('leagues.relatedUsers.remove', $league), ['user_id' => $admin->id])
            ->assertSessionHas('error', RelatedRosterInvite::ADMIN_MEMBERSHIP_MESSAGE);
    }

    public function test_backfill_adds_existing_admins_to_rosters(): void
    {
        $admin = $this->userWithPlayer('Stary Admin');
        $organization = Organization::query()->create(['name' => 'Stary klub', 'description' => 'Opis']);
        $organization->admins()->attach($admin->id);

        $season = Season::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Stary sezon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $season->admins()->attach($admin->id);

        $league = League::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Stara liga',
            'description' => null,
        ]);

        DB::table('organization_user')->where('user_id', $admin->id)->delete();
        DB::table('season_user')->where('user_id', $admin->id)->delete();
        DB::table('league_user')->where('user_id', $admin->id)->delete();

        $migration = require database_path('migrations/2026_09_21_210000_backfill_admin_related_users.php');
        $migration->up();

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $admin->id));
        $this->assertTrue($season->fresh()->relatedUsers->contains('id', $admin->id));
        $this->assertTrue($league->fresh()->relatedUsers->contains('id', $admin->id));

        $migration->up();
        $this->assertSame(1, $organization->fresh()->relatedUsers->where('id', $admin->id)->count());
    }

    public function test_admin_joins_tournament_immediately_without_push(): void
    {
        Queue::fake();

        $admin = $this->userWithPlayer('Admin Turnieju');
        $other = $this->userWithPlayer('Inny');
        $tournament = $this->tournamentFor($admin);

        $this->actingAs($admin)
            ->postJson(route('tournaments.invitations.join-self', $tournament))
            ->assertOk()
            ->assertJsonPath('message', 'Dodano Cię do turnieju')
            ->assertJsonPath('participantCount', 1);

        $this->assertSame(1, TournamentInvitation::query()->where('tournament_id', $tournament->id)->count());
        $this->assertDatabaseHas('tournament_invitations', [
            'tournament_id' => $tournament->id,
            'user_id' => $admin->id,
            'status' => TournamentInvitationStatus::ACCEPTED->value,
        ]);
        Queue::assertNotPushed(SendInvitationPushJob::class);

        $this->actingAs($admin)
            ->postJson(route('tournaments.invitations.join-self', $tournament))
            ->assertOk();

        $this->assertSame(1, TournamentInvitation::query()->where('tournament_id', $tournament->id)->count());

        $this->actingAs($admin)
            ->postJson(route('tournaments.invitations.send', $tournament), ['user_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('message', 'Dodano Cię do turnieju');

        Queue::assertNotPushed(SendInvitationPushJob::class);

        $result = app(TournamentInvitationService::class)->sendBulk($tournament->id, [$admin->id, $other->id], $admin->id);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($other) {
            return $job->recipientUserId === $other->id;
        });
        Queue::assertNotPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($admin) {
            return $job->recipientUserId === $admin->id;
        });
    }

    public function test_join_self_is_blocked_after_tournament_starts(): void
    {
        $admin = $this->userWithPlayer('Admin Start');
        $tournament = $this->tournamentFor($admin);
        $tournament->update(['status' => TournamentStatus::GROUP]);

        $this->actingAs($admin)
            ->postJson(route('tournaments.invitations.join-self', $tournament))
            ->assertStatus(422);

        $this->assertDatabaseMissing('tournament_invitations', [
            'tournament_id' => $tournament->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_join_self(): void
    {
        $admin = $this->userWithPlayer('Admin');
        $stranger = $this->userWithPlayer('Obcy');
        $tournament = $this->tournamentFor($admin);

        $this->actingAs($stranger)
            ->postJson(route('tournaments.invitations.join-self', $tournament))
            ->assertForbidden();
    }

    private function userWithPlayer(string $name): User
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create($name, $user->id);

        return $user;
    }

    private function tournamentFor(User $admin): Tournament
    {
        $organization = Organization::query()->create(['name' => 'Klub '.$admin->id, 'description' => 'Opis']);
        $season = Season::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sezon '.$admin->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $tournament = Tournament::query()->create([
            'season_id' => $season->id,
            'name' => 'Puchar '.$admin->id,
            'date' => '2026-06-01',
            'status' => TournamentStatus::CREATED,
        ]);
        $tournament->admins()->attach($admin->id);

        return $tournament;
    }
}
