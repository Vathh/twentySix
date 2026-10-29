<?php

namespace Tests\Feature;

use App\Domain\Retention\ApplicationEntityKind;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use App\Services\Retention\ApplicationEntityDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OneOffTournamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tournament_cannot_be_created_without_an_organization(): void
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Admin', $admin->id);

        $this->actingAs($admin)
            ->get(route('tournaments.create'))
            ->assertRedirect(route('tournaments.index'));

        $this->actingAs($admin)
            ->post(route('tournaments.store'), [
                'tournamentName' => 'Luzny',
                'date' => '2026-11-01',
            ])
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseCount('tournaments', 0);
    }

    public function test_organization_admin_creates_a_one_off_tournament_outside_any_season(): void
    {
        $admin = User::factory()->create();
        app(PlayerService::class)->create('Admin', $admin->id);
        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $admin->id);

        $stranger = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Obcy', $stranger->id);

        $this->actingAs($stranger)
            ->post(route('tournaments.store'), [
                'organizationId' => $organization->id,
                'tournamentName' => 'NIŚ',
                'date' => '2026-11-01',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('tournaments.create', ['organizationId' => $organization->id]))
            ->assertOk()
            ->assertSee('Nie wchodzi do żadnego sezonu');

        $this->actingAs($admin)
            ->post(route('tournaments.store'), [
                'organizationId' => $organization->id,
                'tournamentName' => 'NIŚ',
                'date' => '2026-11-01',
            ])
            ->assertRedirect();

        $tournament = Tournament::query()->where('name', 'NIŚ')->first();
        $this->assertNotNull($tournament);
        $this->assertNull($tournament->season_id);
        $this->assertSame($organization->id, (int) $tournament->organization_id);
        $this->assertTrue($tournament->admins()->whereKey($admin->id)->exists());

        $this->actingAs($admin)
            ->get(route('tournaments.start', $tournament))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('organizations.show', $organization->id))
            ->assertOk()
            ->assertSee('Turnieje jednorazowe')
            ->assertSee('NIŚ')
            ->assertSee('Dodaj turniej');

        $this->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Turniej jednorazowy')
            ->assertSee('Apagon')
            ->assertSee(route('organizations.show', $organization->id), false)
            ->assertDontSee('place-slot--season', false);

        Sanctum::actingAs($admin);
        $this->getJson('/api/organizations/'.$organization->id)
            ->assertOk()
            ->assertJsonPath('oneOffTournaments.0.name', 'NIŚ')
            ->assertJsonPath('oneOffTournaments.0.id', $tournament->id);
    }

    public function test_deleting_an_organization_hides_its_one_off_tournament(): void
    {
        $admin = User::factory()->create();
        app(PlayerService::class)->create('Admin', $admin->id);
        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $admin->id);

        $this->actingAs($admin)->post(route('tournaments.store'), [
            'organizationId' => $organization->id,
            'tournamentName' => 'NIŚ',
            'date' => '2026-11-01',
        ])->assertRedirect();

        $tournament = Tournament::query()->where('name', 'NIŚ')->firstOrFail();

        app(ApplicationEntityDeletionService::class)->hide(
            ApplicationEntityKind::Organization,
            $organization->id,
            $admin->id,
        );

        $this->assertSoftDeleted('tournaments', ['id' => $tournament->id]);
    }
}
