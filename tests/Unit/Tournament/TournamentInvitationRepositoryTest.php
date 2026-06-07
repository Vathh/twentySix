<?php

namespace Tests\Unit\Tournament;

use App\Enums\TournamentInvitationStatus;
use App\Models\Tournament\TournamentInvitation;
use App\Models\Users\User;
use App\Repositories\Tournament\TournamentInvitationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentInvitationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TournamentInvitationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TournamentInvitationRepository::class);
    }

    public function test_create_and_accept_invitation(): void
    {
        [$tournament, $invited, $admin] = $this->seedTournamentWithUsers();

        $domain = $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);

        $this->assertSame(TournamentInvitationStatus::PENDING, $domain->status);

        $this->repository->accept($domain->id, $invited->id);

        $this->assertDatabaseHas('tournament_invitations', [
            'id' => $domain->id,
            'status' => TournamentInvitationStatus::ACCEPTED->value,
        ]);
    }

    public function test_reinvite_after_rejection(): void
    {
        [$tournament, $invited, $admin] = $this->seedTournamentWithUsers();

        $domain = $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);
        $this->repository->reject($domain->id, $invited->id);

        $reinvited = $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);

        $this->assertSame(TournamentInvitationStatus::PENDING, $reinvited->status);
        $this->assertSame($domain->id, $reinvited->id);
    }

    public function test_cannot_create_duplicate_active_invitation(): void
    {
        [$tournament, $invited, $admin] = $this->seedTournamentWithUsers();

        $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);

        $this->expectException(\RuntimeException::class);
        $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);
    }

    public function test_withdraw_after_accept(): void
    {
        [$tournament, $invited, $admin] = $this->seedTournamentWithUsers();

        $domain = $this->repository->createOrReinvite($tournament->id, $invited->id, $admin->id);
        $this->repository->accept($domain->id, $invited->id);
        $this->repository->withdraw($domain->id, $invited->id);

        $this->assertDatabaseHas('tournament_invitations', [
            'id' => $domain->id,
            'status' => TournamentInvitationStatus::WITHDRAWN->value,
        ]);
    }

    /**
     * @return array{0: \App\Models\Tournament\Tournament, 1: User, 2: User}
     */
    private function seedTournamentWithUsers(): array
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PointSchemeSeeder']);

        $admin = User::factory()->create();
        $invited = User::factory()->create();

        $league = \App\Models\League\League::create(['name' => 'Test League', 'description' => '']);
        $season = \App\Models\Season\Season::create([
            'league_id' => $league->id,
            'name' => 'Season 1',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ]);
        $tournament = \App\Models\Tournament\Tournament::create([
            'season_id' => $season->id,
            'name' => 'T1',
            'date' => now(),
            'status' => 'created',
        ]);

        return [$tournament, $invited, $admin];
    }
}
