<?php

namespace Tests\Feature;

use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentGuestParticipant;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Support\Roster\SeasonRosterMigrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeasonRosterMigratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_season_members_pending_invites_and_guests_onto_the_organization(): void
    {
        $this->recreateDroppedSeasonRosterTables();

        $admin = User::factory()->create();
        app(PlayerService::class)->create('Admin', $admin->id);
        $member = User::factory()->create();
        app(PlayerService::class)->create('Członek', $member->id);
        $invitee = User::factory()->create();
        app(PlayerService::class)->create('Zaproszony', $invitee->id);

        $organization = Organization::create(['name' => 'Klub', 'description' => 'Opis']);
        $organization->relatedUsers()->attach($admin->id);
        Player::create([
            'name' => 'Jan',
            'organization_id' => $organization->id,
        ]);

        $season = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Sezon',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $otherSeason = Season::create([
            'organization_id' => $organization->id,
            'name' => 'Drugi',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        DB::table('season_user')->insert([
            'season_id' => $season->id,
            'user_id' => $member->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('season_user')->insert([
            'season_id' => $season->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('season_invitations')->insert([
            'season_id' => $season->id,
            'user_id' => $invitee->id,
            'invited_by' => $admin->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guest = Player::create([
            'name' => 'Jan',
            'season_id' => $season->id,
        ]);
        $secondGuest = Player::create([
            'name' => 'Jan',
            'season_id' => $otherSeason->id,
        ]);
        $tournament = Tournament::create([
            'name' => 'Open',
            'season_id' => $season->id,
            'date' => '2026-06-01',
        ]);
        TournamentGuestParticipant::query()->create([
            'tournament_id' => $tournament->id,
            'player_id' => $guest->id,
        ]);

        (new SeasonRosterMigrator)->move();

        $this->assertTrue($organization->fresh()->relatedUsers->contains('id', $member->id));
        $this->assertSame(1, $organization->fresh()->relatedUsers->where('id', $admin->id)->count());
        $this->assertDatabaseHas('organization_invitations', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
            'status' => 'pending',
        ]);

        $guest->refresh();
        $secondGuest->refresh();
        $this->assertSame($organization->id, $guest->organization_id);
        $this->assertNull($guest->season_id);
        $this->assertSame($guest->id, TournamentGuestParticipant::query()->where('tournament_id', $tournament->id)->value('player_id'));
        $this->assertSame($organization->id, $secondGuest->organization_id);
        $this->assertNull($secondGuest->season_id);
        $this->assertSame(
            ['Jan (2)', 'Jan (3)'],
            collect([$guest->name, $secondGuest->name])->sort()->values()->all(),
        );
        $this->assertDatabaseHas('players', [
            'name' => 'Jan',
            'organization_id' => $organization->id,
            'user_id' => null,
        ]);
    }

    private function recreateDroppedSeasonRosterTables(): void
    {
        Schema::create('season_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('season_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invited_by');
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }
}
