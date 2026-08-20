<?php

namespace Tests\Feature;

use App\Enums\LeagueCalendarMode;
use App\Enums\LeagueSeasonStatus;
use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\League\LeagueService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueRosterPoolTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Admin', $this->admin->id);

        $this->organization = Organization::create(['name' => 'Klub', 'description' => '']);
        $this->organization->admins()->attach($this->admin->id);

        $this->league = app(LeagueService::class)->create($this->organization->id, 'Pucharowa', null, [[
            'name' => 'Ekstraklasa',
            'capacity' => 8,
            'startingScore' => 501,
            'legsToWinSet' => 2,
            'setsToWinMatch' => 1,
            'promoteDirect' => 0,
            'promotePlayoff' => 0,
        ]]);
    }

    #[Test]
    public function admin_adds_related_user_and_guest_then_assigns_them_separately(): void
    {
        $this->actingAs($this->admin);

        $related = User::factory()->create();
        app(PlayerService::class)->create('Anna', $related->id);
        $relatedPlayer = Player::query()->where('user_id', $related->id)->first();

        $this->post(route('leagues.relatedUsers.add', $this->league), [
            'user_id' => $related->id,
        ])->assertRedirect(route('leagues.relatedUsers', $this->league));

        $this->post(route('leagues.guests.add', $this->league), [
            'name' => 'Gość Liga',
        ])->assertRedirect(route('leagues.guests', $this->league));

        $guest = Player::query()->where('name', 'Gość Liga')->where('league_id', $this->league->id)->first();
        $this->assertNotNull($guest);

        $roster = app(LeagueService::class)->rosterData($this->league->id);
        $this->assertTrue($roster['availableRelatedPlayers']->contains('id', $relatedPlayer->id));
        $this->assertTrue($roster['availableGuests']->contains('id', $guest->id));
        $this->assertFalse($roster['availableRelatedPlayers']->contains('id', $guest->id));
        $this->assertFalse($roster['availableGuests']->contains('id', $relatedPlayer->id));

        $divisionId = $this->league->divisions->first()->id;
        $this->post(route('leagues.roster.assign', $this->league), [
            'division_id' => $divisionId,
            'player_id' => $relatedPlayer->id,
        ])->assertRedirect();
        $this->post(route('leagues.roster.assign', $this->league), [
            'division_id' => $divisionId,
            'player_id' => $guest->id,
        ])->assertRedirect();

        $this->assertTrue($this->league->fresh()->members->pluck('player_id')->contains($relatedPlayer->id));
        $this->assertTrue($this->league->fresh()->members->pluck('player_id')->contains($guest->id));
    }

    #[Test]
    public function roster_assign_and_remove_accept_json(): void
    {
        $this->actingAs($this->admin);

        $related = User::factory()->create();
        app(PlayerService::class)->create('Celina', $related->id);
        $player = Player::query()->where('user_id', $related->id)->first();
        $this->league->relatedUsers()->attach($related->id);
        $divisionId = $this->league->divisions->first()->id;

        $this->postJson(route('leagues.roster.assign', $this->league), [
            'division_id' => $divisionId,
            'player_id' => $player->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertTrue($this->league->fresh()->members->pluck('player_id')->contains($player->id));

        $this->deleteJson(route('leagues.roster.remove', $this->league), [
            'player_id' => $player->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertFalse($this->league->fresh()->members->pluck('player_id')->contains($player->id));
    }

    #[Test]
    public function organization_related_user_is_not_eligible_until_added_to_league_pool(): void
    {
        $outsider = User::factory()->create();
        app(PlayerService::class)->create('Obcy', $outsider->id);
        $player = Player::query()->where('user_id', $outsider->id)->first();
        $this->organization->relatedUsers()->attach($outsider->id);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LeagueService::class)->assignPlayer(
            $this->league->id,
            $this->league->divisions->first()->id,
            $player->id,
        );
    }

    #[Test]
    public function admin_can_increase_and_decrease_division_capacity(): void
    {
        $this->actingAs($this->admin);
        $division = $this->league->divisions->first();

        $this->patchJson(route('leagues.roster.capacity', $this->league), [
            'division_id' => $division->id,
            'capacity' => 10,
        ])->assertOk()->assertJsonPath('capacity', 10);

        $this->assertSame(10, $division->fresh()->capacity);

        $this->patchJson(route('leagues.roster.capacity', $this->league), [
            'division_id' => $division->id,
            'capacity' => 6,
        ])->assertOk()->assertJsonPath('capacity', 6);

        $this->assertSame(6, $division->fresh()->capacity);
    }

    #[Test]
    public function cannot_decrease_capacity_below_member_count(): void
    {
        $this->actingAs($this->admin);
        $division = $this->league->divisions->first();
        $leagueService = app(LeagueService::class);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create();
            app(PlayerService::class)->create('Gracz '.$i, $user->id);
            $this->league->relatedUsers()->attach($user->id);
            $player = Player::query()->where('user_id', $user->id)->first();
            $leagueService->assignPlayer($this->league->id, $division->id, $player->id);
        }

        $this->patchJson(route('leagues.roster.capacity', $this->league), [
            'division_id' => $division->id,
            'capacity' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['capacity']);

        $this->assertSame(8, $division->fresh()->capacity);
    }

    #[Test]
    public function cannot_change_capacity_when_season_locks_pyramid(): void
    {
        $this->actingAs($this->admin);
        $division = $this->league->divisions->first();

        LeagueSeason::create([
            'league_id' => $this->league->id,
            'name' => 'Sezon 1',
            'status' => LeagueSeasonStatus::IN_PROGRESS,
            'calendar_mode' => LeagueCalendarMode::DEADLINE,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->patchJson(route('leagues.roster.capacity', $this->league), [
            'division_id' => $division->id,
            'capacity' => 10,
        ])->assertStatus(400);

        $this->assertSame(8, $division->fresh()->capacity);
    }

    #[Test]
    public function removing_league_guest_does_not_delete_organization_guest(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('organizations.guests.add', $this->organization->id), [
            'name' => 'Bartek',
        ])->assertRedirect();

        $orgGuest = Player::query()
            ->where('name', 'Bartek')
            ->where('organization_id', $this->organization->id)
            ->first();
        $this->assertNotNull($orgGuest);

        $orgGuest->update(['league_id' => $this->league->id]);

        $this->delete(route('leagues.guests.remove', $this->league), [
            'player_id' => $orgGuest->id,
        ])->assertRedirect(route('leagues.guests', $this->league));

        $orgGuest->refresh();
        $this->assertSame($this->organization->id, $orgGuest->organization_id);
        $this->assertNull($orgGuest->league_id);
        $this->assertFalse($this->league->fresh()->guests->contains('id', $orgGuest->id));
        $this->assertTrue($this->organization->fresh()->guests->contains('id', $orgGuest->id));
    }

    #[Test]
    public function removing_league_only_guest_deletes_that_player(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('leagues.guests.add', $this->league), [
            'name' => 'Gość Liga',
        ])->assertRedirect();

        $guest = Player::query()
            ->where('name', 'Gość Liga')
            ->where('league_id', $this->league->id)
            ->first();
        $this->assertNotNull($guest);

        $this->delete(route('leagues.guests.remove', $this->league), [
            'player_id' => $guest->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('players', ['id' => $guest->id]);
    }

    #[Test]
    public function removing_organization_guest_does_not_delete_league_guest(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('organizations.guests.add', $this->organization->id), [
            'name' => 'Bartek',
        ])->assertRedirect();
        $this->post(route('leagues.guests.add', $this->league), [
            'name' => 'Bartek',
        ])->assertRedirect();

        $orgGuest = Player::query()
            ->where('name', 'Bartek')
            ->where('organization_id', $this->organization->id)
            ->first();
        $leagueGuest = Player::query()
            ->where('name', 'Bartek')
            ->where('league_id', $this->league->id)
            ->first();
        $this->assertNotNull($orgGuest);
        $this->assertNotNull($leagueGuest);
        $this->assertNotSame($orgGuest->id, $leagueGuest->id);

        $this->delete(route('organizations.guests.remove', $this->organization->id), [
            'player_id' => $orgGuest->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('players', ['id' => $orgGuest->id]);
        $this->assertDatabaseHas('players', [
            'id' => $leagueGuest->id,
            'league_id' => $this->league->id,
        ]);
    }

    #[Test]
    public function related_user_search_returns_json_without_full_page(): void
    {
        $this->actingAs($this->admin);

        $found = User::factory()->create();
        app(PlayerService::class)->create('Nowak Jan', $found->id);
        $already = User::factory()->create();
        app(PlayerService::class)->create('Nowak Anna', $already->id);
        $this->league->relatedUsers()->attach($already->id);

        $this->getJson(route('leagues.relatedUsers', $this->league).'?q=Now')
            ->assertStatus(422);

        $this->getJson(route('leagues.relatedUsers', $this->league).'?q=Nowak')
            ->assertOk()
            ->assertJsonPath('users.0.name', 'Nowak Jan')
            ->assertJsonMissing(['name' => 'Nowak Anna']);
    }

    #[Test]
    public function related_user_add_accepts_json_without_redirect(): void
    {
        $this->actingAs($this->admin);

        $found = User::factory()->create();
        app(PlayerService::class)->create('Nowak Jan', $found->id);

        $this->postJson(route('leagues.relatedUsers.add', $this->league), [
            'user_id' => $found->id,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.id', $found->id)
            ->assertJsonPath('user.name', 'Nowak Jan');

        $this->assertTrue($this->league->fresh()->relatedUsers->contains('id', $found->id));
    }
}
