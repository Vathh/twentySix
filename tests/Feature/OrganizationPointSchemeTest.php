<?php

namespace Tests\Feature;

use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\SeasonPointTable;
use App\Enums\PointSchemeFormat;
use App\Enums\TournamentFormat;
use App\Models\Organization\Organization;
use App\Models\Player\Player;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Tournament\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPointSchemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_admin_cannot_open_the_form(): void
    {
        $organization = $this->organizationWithAdmin();

        $this->get(route('organizations.point-scheme.edit', $organization))
            ->assertForbidden();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->get(route('organizations.point-scheme.edit', $organization))
            ->assertForbidden();
    }

    public function test_admin_sees_system_defaults(): void
    {
        [$admin, $organization] = $this->adminAndOrganization();

        $this->actingAs($admin)
            ->get(route('organizations.point-scheme.edit', $organization))
            ->assertOk()
            ->assertSee('Punktacja turniejowa')
            ->assertSee('od 4 do 8 osób')
            ->assertSee('Te liczby są takie jak domyślna punktacja twentySix');
    }

    public function test_custom_table_applies_on_start_and_stays_frozen(): void
    {
        [$admin, $organization] = $this->adminAndOrganization();
        $players = $this->players(4);

        $payload = $this->payload();
        $payload['bands'][4]['se'] = [
            ['place_from' => 1, 'place_to' => 1, 'points' => 99],
            ['place_from' => 2, 'place_to' => 2, 'points' => 10],
            ['place_from' => 3, 'place_to' => 3, 'points' => 8],
            ['place_from' => 4, 'place_to' => 4, 'points' => 6],
            ['place_from' => 5, 'place_to' => 5, 'points' => 5],
            ['place_from' => 6, 'place_to' => 6, 'points' => 4],
            ['place_from' => 7, 'place_to' => 7, 'points' => 3],
            ['place_from' => 8, 'place_to' => 8, 'points' => 2],
        ];

        $this->actingAs($admin)
            ->put(route('organizations.point-scheme.update', $organization), $payload)
            ->assertRedirect(route('organizations.point-scheme.edit', $organization))
            ->assertSessionHas('success');

        $firstIds = PointScheme::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('min_players')
            ->pluck('id');

        $this->actingAs($admin)
            ->put(route('organizations.point-scheme.update', $organization), $payload)
            ->assertSessionHas('success');

        $replacedIds = PointScheme::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('min_players')
            ->pluck('id');
        $this->assertSame($firstIds->all(), $replacedIds->all());

        $started = $this->startSingleElimination($organization, $players);
        $frozenId = $started->point_scheme_id;
        $scheme = $this->scheme($frozenId);

        $this->assertSame($organization->id, PointScheme::query()->findOrFail($frozenId)->organization_id);
        $this->assertSame(99, $scheme->pointsForPlace(TournamentFormat::SingleElimination, 1));
        $this->assertSame(99, $scheme->pointsForPlace(TournamentFormat::GroupsPlayoff, 1));
        $this->assertSame(5, $scheme->pointsForPlace(TournamentFormat::SingleElimination, 5));
        $this->assertSame(13, $scheme->pointsForPlace(TournamentFormat::DoubleElimination, 1));

        $payload['bands'][4]['se'][0]['points'] = 77;
        $this->actingAs($admin)
            ->put(route('organizations.point-scheme.update', $organization), $payload)
            ->assertSessionHas('success');

        $started->refresh();
        $this->assertSame($frozenId, $started->point_scheme_id);
        $this->assertFalse((bool) PointScheme::query()->findOrFail($frozenId)->is_active);
        $this->assertSame(99, $this->scheme($frozenId)->pointsForPlace(TournamentFormat::SingleElimination, 1));

        $next = $this->startSingleElimination($organization, $players);
        $this->assertNotSame($frozenId, $next->point_scheme_id);
        $this->assertTrue((bool) PointScheme::query()->findOrFail($next->point_scheme_id)->is_active);
        $this->assertSame(77, $this->scheme($next->point_scheme_id)->pointsForPlace(TournamentFormat::SingleElimination, 1));
    }

    public function test_organization_without_custom_table_uses_system_scheme(): void
    {
        $organization = Organization::create(['name' => 'Bez własnej tabeli', 'description' => '']);
        $players = $this->players(4);

        $started = $this->startSingleElimination($organization, $players);
        $scheme = PointScheme::query()->findOrFail($started->point_scheme_id);

        $this->assertNull($scheme->organization_id);
        $this->assertSame(13, $this->scheme($scheme->id)->pointsForPlace(TournamentFormat::SingleElimination, 1));
    }

    public function test_invalid_ranges_are_rejected_and_do_not_create_a_copy(): void
    {
        [$admin, $organization] = $this->adminAndOrganization();
        $payload = $this->payload();
        unset($payload['bands'][4]['se'][2]);

        $this->actingAs($admin)
            ->from(route('organizations.point-scheme.edit', $organization))
            ->put(route('organizations.point-scheme.update', $organization), $payload)
            ->assertRedirect(route('organizations.point-scheme.edit', $organization))
            ->assertSessionHasErrors();

        $this->assertSame(0, PointScheme::query()->where('organization_id', $organization->id)->count());
    }

    public function test_reset_restores_default_points_without_rewriting_started_tournament(): void
    {
        [$admin, $organization] = $this->adminAndOrganization();
        $players = $this->players(4);
        $payload = $this->payload();
        $payload['bands'][4]['se'][0]['points'] = 99;

        $this->actingAs($admin)
            ->put(route('organizations.point-scheme.update', $organization), $payload)
            ->assertSessionHas('success');

        $started = $this->startSingleElimination($organization, $players);

        $this->actingAs($admin)
            ->post(route('organizations.point-scheme.reset', $organization))
            ->assertRedirect(route('organizations.point-scheme.edit', $organization))
            ->assertSessionHas('success');

        $started->refresh();
        $this->assertSame(99, $this->scheme($started->point_scheme_id)->pointsForPlace(TournamentFormat::SingleElimination, 1));
        $this->assertFalse((bool) PointScheme::query()->findOrFail($started->point_scheme_id)->is_active);

        $active = PointScheme::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where('min_players', 4)
            ->firstOrFail();
        $this->assertSame(13, PointSchemeRule::query()
            ->where('point_scheme_id', $active->id)
            ->where('format', PointSchemeFormat::Se->value)
            ->where('place_from', 1)
            ->value('points'));
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function adminAndOrganization(): array
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        Player::create(['name' => 'Admin ligi', 'user_id' => $admin->id]);
        $organization = Organization::create(['name' => 'Liga testowa', 'description' => '']);
        $organization->admins()->attach($admin->id);

        return [$admin, $organization];
    }

    private function organizationWithAdmin(): Organization
    {
        [, $organization] = $this->adminAndOrganization();

        return $organization;
    }

    /**
     * @return list<Player>
     */
    private function players(int $count): array
    {
        $players = [];
        for ($i = 1; $i <= $count; $i++) {
            $players[] = Player::create(['name' => 'Zawodnik '.$i]);
        }

        return $players;
    }

    /**
     * @param  list<Player>  $players
     */
    private function startSingleElimination(Organization $organization, array $players): Tournament
    {
        $season = Season::create([
            'name' => 'Sezon '.$organization->id.'-'.Season::query()->count(),
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $tournament = Tournament::create([
            'name' => 'SE '.$season->id,
            'season_id' => $season->id,
            'date' => '2026-06-01',
        ]);

        $started = app(TournamentService::class)->tryStartSingleElimination(
            $tournament->id,
            array_map(fn (Player $player) => $player->id, $players),
        );
        $this->assertTrue($started);

        return $tournament->fresh();
    }

    private function scheme(int $id): PointSchemeDomain
    {
        return PointSchemeDomain::fromEloquent(
            PointScheme::query()->with('rules')->findOrFail($id),
            ['rules'],
        );
    }

    /**
     * @return array{bands: array<int, array{se: list<array{place_from: int, place_to: int, points: int}>, de: list<array{place_from: int, place_to: int, points: int}>}>}
     */
    private function payload(): array
    {
        $bands = [];
        foreach (SeasonPointTable::definitions() as $band) {
            $min = $band['min_players'];
            $bands[$min] = ['se' => [], 'de' => []];
            foreach ($band['rules'] as $rule) {
                $bands[$min][$rule['format']->value][] = [
                    'place_from' => $rule['place_from'],
                    'place_to' => $rule['place_to'],
                    'points' => $rule['points'],
                ];
            }
        }

        return ['bands' => $bands];
    }
}
