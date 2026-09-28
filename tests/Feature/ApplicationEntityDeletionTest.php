<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\League\League;
use App\Models\League\LeagueSeason;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentResult;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationEntityDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'can_create_organizations' => true,
        ]);
        app(PlayerService::class)->create('Admin', $this->admin->id);

        $this->organization = Organization::create(['name' => 'Klub', 'description' => 'Test']);
        $this->organization->admins()->attach($this->admin->id);
    }

    public function test_wrong_password_and_stranger_cannot_delete_organization(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('organizations.destroy', $this->organization), [
                'delete_password' => 'not-the-password',
                'entity_name_confirmation' => $this->organization->name,
            ])
            ->assertSessionHasErrors('delete_password');

        $this->assertNotSoftDeleted($this->organization);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->delete(route('organizations.destroy', $this->organization), [
                'delete_password' => 'password',
                'entity_name_confirmation' => $this->organization->name,
            ])
            ->assertForbidden();
    }

    public function test_deleting_organization_hides_children_and_restore_skips_independent_root(): void
    {
        $season = $this->makeSeason();
        $tournament = $this->makeTournament($season);
        $league = $this->makeLeague();
        $kept = $this->makeLeagueSeason($league, 'Sezon zostaje z ligą');
        $independent = $this->makeLeagueSeason($league, 'Sezon usunięty osobno');

        $this->actingAs($this->admin)
            ->delete(route('league-seasons.destroy', $independent), $this->confirm($independent->name))
            ->assertRedirect(route('leagues.show', $league));

        $this->actingAs($this->admin)
            ->delete(route('organizations.destroy', $this->organization), $this->confirm($this->organization->name))
            ->assertRedirect(route('organizations.index'));

        $this->get(route('organizations.show', $this->organization))->assertNotFound();
        $this->get(route('tournaments.show', $tournament))->assertNotFound();
        $this->get(route('leagues.show', $league))->assertNotFound();
        $this->get(route('league-seasons.show', $kept))->assertNotFound();

        $independent->refresh();
        $this->assertNotNull($independent->deleted_at);
        $this->assertNull($independent->cascade_from_type);

        $kept->refresh();
        $this->assertSame('organization', $kept->cascade_from_type);
        $this->assertSame($this->organization->id, (int) $kept->cascade_from_id);

        $platform = $this->makePlatformAdmin();
        $this->actingAs($platform)
            ->post(route('admin.deleted.restore', ['kind' => 'organization', 'id' => $this->organization->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($this->organization);
        $this->assertNotSoftDeleted($season);
        $this->assertNotSoftDeleted($tournament);
        $this->assertNotSoftDeleted($league);
        $this->assertNotSoftDeleted($kept);
        $this->assertSoftDeleted($independent);
    }

    public function test_league_restore_does_not_bring_back_separately_deleted_league_season(): void
    {
        $league = $this->makeLeague();
        $withLeague = $this->makeLeagueSeason($league, 'Razem z ligą');
        $alone = $this->makeLeagueSeason($league, 'Osobno');

        $this->actingAs($this->admin)->delete(route('league-seasons.destroy', $alone), $this->confirm($alone->name));
        $this->actingAs($this->admin)->delete(route('leagues.destroy', $league), $this->confirm($league->name));

        $platform = $this->makePlatformAdmin();
        $this->actingAs($platform)
            ->post(route('admin.deleted.restore', ['kind' => 'league', 'id' => $league->id]))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($league);
        $this->assertNotSoftDeleted($withLeague);
        $this->assertSoftDeleted($alone);
    }

    public function test_hidden_league_name_can_be_used_again(): void
    {
        $league = $this->makeLeague('Pucharowa');

        $this->actingAs($this->admin)
            ->delete(route('leagues.destroy', $league), $this->confirm('Pucharowa'));

        $this->actingAs($this->admin)
            ->post(route('leagues.store', $this->organization), [
                'leagueName' => 'Pucharowa',
                'description' => null,
                'divisions' => [[
                    'name' => 'Ekstraklasa',
                    'capacity' => 4,
                    'startingScore' => 501,
                    'legsToWinSet' => 2,
                    'setsToWinMatch' => 1,
                    'promoteDirect' => 0,
                    'promotePlayoff' => 0,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(2, League::withTrashed()->where('name', 'Pucharowa')->count());
        $this->assertSame(1, League::query()->where('name', 'Pucharowa')->count());
    }

    public function test_purge_removes_expired_root_and_keeps_fresh_one_until_blocker_expires(): void
    {
        $season = $this->makeSeason();
        $tournament = $this->makeTournament($season);
        $player = Player::create(['name' => 'Zawodnik']);
        $opponent = Player::create(['name' => 'Rywal']);
        $game = Game::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $player->id,
            'player2_id' => $opponent->id,
            'group_number' => 1,
            'status' => GameStatus::FINISHED,
        ]);
        $leg = GameLeg::create([
            'game_id' => $game->id,
            'leg_number' => 1,
        ]);
        TournamentResult::create([
            'season_id' => $season->id,
            'tournament_id' => $tournament->id,
            'player_id' => $player->id,
            'points' => 3,
            'place' => 1,
        ]);

        $league = $this->makeLeague();
        $blockingSeason = $this->makeLeagueSeason($league, 'Blokuje organizację');

        $this->actingAs($this->admin)->delete(route('tournaments.destroy', $tournament), $this->confirm($tournament->name));
        $this->actingAs($this->admin)->delete(route('league-seasons.destroy', $blockingSeason), $this->confirm($blockingSeason->name));
        $this->actingAs($this->admin)->delete(route('organizations.destroy', $this->organization), $this->confirm($this->organization->name));

        Tournament::withTrashed()->whereKey($tournament->id)->update(['deleted_at' => now()->subDays(91)]);
        Organization::withTrashed()->whereKey($this->organization->id)->update(['deleted_at' => now()->subDays(91)]);

        $this->artisan('application-entities:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        $this->assertDatabaseMissing('game_legs', ['id' => $leg->id]);
        $this->assertDatabaseMissing('tournament_results', ['tournament_id' => $tournament->id]);
        $this->assertSoftDeleted($this->organization);
        $this->assertSoftDeleted($blockingSeason);

        LeagueSeason::withTrashed()->whereKey($blockingSeason->id)->update(['deleted_at' => now()->subDays(91)]);

        $this->artisan('application-entities:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $this->organization->id]);
        $this->assertDatabaseMissing('league_seasons', ['id' => $blockingSeason->id]);
        $this->assertDatabaseMissing('leagues', ['id' => $league->id]);
        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
    }

    private function confirm(string $name): array
    {
        return [
            'delete_password' => 'password',
            'entity_name_confirmation' => $name,
        ];
    }

    private function makePlatformAdmin(): User
    {
        $user = User::factory()->create(['email' => 'owner@test.com']);
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    private function makeSeason(): Season
    {
        $season = Season::create([
            'name' => 'Sezon 2026',
            'organization_id' => $this->organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $season->admins()->attach($this->admin->id);

        return $season;
    }

    private function makeTournament(Season $season): Tournament
    {
        $tournament = Tournament::create([
            'name' => 'Turniej maj',
            'season_id' => $season->id,
            'date' => '2026-05-01',
            'status' => TournamentStatus::CREATED,
        ]);
        $tournament->admins()->attach($this->admin->id);

        return $tournament;
    }

    private function makeLeague(string $name = 'Liga A'): League
    {
        return League::create([
            'organization_id' => $this->organization->id,
            'name' => $name,
        ]);
    }

    private function makeLeagueSeason(League $league, string $name): LeagueSeason
    {
        return LeagueSeason::create([
            'league_id' => $league->id,
            'name' => $name,
            'status' => 'draft',
            'calendar_mode' => 'deadline',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-01',
        ]);
    }
}
