<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Repositories\Organization\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogNameSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_search_ignores_polish_diacritics_both_ways(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Organization::create(['name' => 'Suwałki Darts', 'description' => 'Liga nad jeziorem']);
        Organization::create(['name' => 'Suwalki Open', 'description' => '']);
        Organization::create(['name' => 'Warszawa Darts', 'description' => '']);

        $withoutMarks = $this->getJson(route('organizations.index', ['q' => 'suwalki']))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('summary', '2 organizacje dla „suwalki”');

        $this->assertEqualsCanonicalizing(
            ['Suwałki Darts', 'Suwalki Open'],
            collect($withoutMarks->json('items'))->pluck('name')->all(),
        );

        $withMarks = $this->getJson(route('organizations.index', ['q' => 'SUWAŁKI']))
            ->assertOk()
            ->assertJsonPath('summary', '2 organizacje dla „SUWAŁKI”');

        $this->assertEqualsCanonicalizing(
            ['Suwałki Darts', 'Suwalki Open'],
            collect($withMarks->json('items'))->pluck('name')->all(),
        );

        $this->get(route('organizations.index', ['q' => 'suwałki']))
            ->assertOk()
            ->assertSee('value="suwałki"', false)
            ->assertViewHas('items', function ($items) {
                return collect($items)->pluck('name')->sort()->values()->all()
                    === ['Suwalki Open', 'Suwałki Darts'];
            });
    }

    public function test_organization_search_treats_percent_and_underscore_as_literal(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Organization::create(['name' => '100% Dart', 'description' => '']);
        Organization::create(['name' => 'Sto Dart', 'description' => '']);
        Organization::create(['name' => 'A_B Club', 'description' => '']);
        Organization::create(['name' => 'AXB Club', 'description' => '']);

        $percent = $this->getJson(route('organizations.index', ['q' => '%']))->assertOk();
        $this->assertSame(['100% Dart'], collect($percent->json('items'))->pluck('name')->all());

        $underscore = $this->getJson(route('organizations.index', ['q' => 'a_b']))->assertOk();
        $this->assertSame(['A_B Club'], collect($underscore->json('items'))->pluck('name')->all());
    }

    public function test_organization_search_paginates_matches_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Organization::create(['name' => 'Inna liga', 'description' => '']);
        for ($i = 1; $i <= OrganizationRepository::INDEX_PER_PAGE + 3; $i++) {
            Organization::create(['name' => "Klub Suwałki {$i}", 'description' => '']);
        }

        $this->getJson(route('organizations.index', ['q' => 'suwalki', 'page' => 1]))
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('total', OrganizationRepository::INDEX_PER_PAGE + 3)
            ->assertJsonCount(OrganizationRepository::INDEX_PER_PAGE, 'items');

        $this->getJson(route('organizations.index', ['q' => 'suwalki', 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(3, 'items');
    }

    public function test_season_and_tournament_search_matches_own_name_or_organization(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $suwalki = Organization::create(['name' => 'Suwałki Darts', 'description' => '']);
        $other = Organization::create(['name' => 'Inna', 'description' => '']);

        $spring = Season::create([
            'name' => 'Wiosna',
            'organization_id' => $suwalki->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
        ]);
        Season::create([
            'name' => 'Łódź',
            'organization_id' => $other->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        Season::create([
            'name' => 'Jesień',
            'organization_id' => $other->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
        ]);

        Tournament::create([
            'name' => 'Finał',
            'season_id' => $spring->id,
            'date' => '2026-06-01',
        ]);
        $lodzSeason = Season::query()->where('name', 'Łódź')->firstOrFail();
        Tournament::create([
            'name' => 'Otwarcie',
            'season_id' => $lodzSeason->id,
            'date' => '2026-02-01',
        ]);

        $seasons = $this->getJson(route('seasons.index', ['q' => 'suwalki']))->assertOk();
        $this->assertSame(['Wiosna'], collect($seasons->json('items'))->pluck('name')->all());
        $this->assertSame('Suwałki Darts', $seasons->json('items.0.context'));

        $lodz = $this->getJson(route('seasons.index', ['q' => 'lodz']))->assertOk();
        $this->assertSame(['Łódź'], collect($lodz->json('items'))->pluck('name')->all());

        $tournaments = $this->getJson(route('tournaments.index', ['q' => 'suwałki']))->assertOk();
        $this->assertSame(['Finał'], collect($tournaments->json('items'))->pluck('name')->all());
        $this->assertSame('Suwałki Darts', $tournaments->json('items.0.context'));

        $byTournamentName = $this->getJson(route('tournaments.index', ['q' => 'otwarcie']))->assertOk();
        $this->assertSame(['Otwarcie'], collect($byTournamentName->json('items'))->pluck('name')->all());
    }

    public function test_organizations_sort_by_activity_or_related_users_and_expose_stats(): void
    {
        $user = User::factory()->create(['can_create_organizations' => true]);
        Sanctum::actingAs($user);
        $members = User::factory()->count(3)->create();

        $quiet = Organization::create(['name' => 'Cicha', 'description' => '']);
        $busy = Organization::create(['name' => 'Żywa', 'description' => 'Tego opisu nie ma na kafelku']);
        $quiet->relatedUsers()->attach($members[0]->id);
        $busy->relatedUsers()->attach($members->pluck('id')->all());

        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $busy->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        Tournament::create([
            'name' => 'Puchar',
            'season_id' => $season->id,
            'date' => '2026-06-01',
        ]);

        Organization::query()->whereKey($quiet->id)->update(['updated_at' => now()->addHour()]);
        Organization::query()->whereKey($busy->id)->update(['updated_at' => now()->subDay()]);

        $byActivity = $this->getJson(route('organizations.index'))->assertOk();
        $this->assertSame(
            ['Cicha', 'Żywa'],
            collect($byActivity->json('items'))->pluck('name')->all(),
        );

        $byMembers = $this->getJson(route('organizations.index', ['sort' => 'members']))->assertOk();
        $this->assertSame(
            ['Żywa', 'Cicha'],
            collect($byMembers->json('items'))->pluck('name')->all(),
        );

        $stats = collect($byMembers->json('items.0.stats'))->keyBy('key');
        $this->assertSame(1, $stats['seasons']['value']);
        $this->assertSame('sezon', $stats['seasons']['label']);
        $this->assertSame(1, $stats['tournaments']['value']);
        $this->assertSame('turniej', $stats['tournaments']['label']);
        $this->assertSame(3, $stats['members']['value']);
        $this->assertSame('użytkownicy', $stats['members']['label']);
        $byMembers->assertJsonMissingPath('items.0.description');

        $this->actingAs($user)
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('Dodaj organizację', false)
            ->assertSee('Sortowanie', false)
            ->assertSee('Ostatnia aktywność', false)
            ->assertDontSee('btn-fab', false);
    }

    public function test_tournaments_can_be_filtered_by_status_group(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Liga', 'description' => '']);
        $season = Season::create([
            'name' => 'Sezon',
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        foreach ([
            'Zaplanowany puchar' => TournamentStatus::CREATED,
            'Faza grupowa' => TournamentStatus::GROUP,
            'Drabinka' => TournamentStatus::PLAYOFF,
            'Finał sezonu' => TournamentStatus::FINISHED,
        ] as $name => $status) {
            Tournament::create([
                'name' => $name,
                'season_id' => $season->id,
                'date' => '2026-06-01',
                'status' => $status,
            ]);
        }

        $planned = $this->getJson(route('tournaments.index', ['status' => 'planned']))->assertOk();
        $this->assertSame(['Zaplanowany puchar'], collect($planned->json('items'))->pluck('name')->all());

        $live = $this->getJson(route('tournaments.index', ['status' => 'live']))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Faza grupowa', 'Drabinka'],
            collect($live->json('items'))->pluck('name')->all(),
        );

        $finished = $this->getJson(route('tournaments.index', ['status' => 'finished']))->assertOk();
        $this->assertSame(['Finał sezonu'], collect($finished->json('items'))->pluck('name')->all());

        $this->getJson(route('tournaments.index', ['status' => 'nieznany']))
            ->assertOk()
            ->assertJsonCount(4, 'items');
    }

    public function test_seasons_can_be_filtered_by_date_phase(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $organization = Organization::create(['name' => 'Klub', 'description' => '']);
        foreach ([
            'Nadchodzący' => [now()->addDay(), now()->addMonth()],
            'Trwający' => [now()->subWeek(), now()->addMonth()],
            'Dzisiaj' => [now(), now()],
            'Miniony' => [now()->subMonths(2), now()->subDay()],
            'Odwrócony' => [now()->addMonth(), now()->subDay()],
            'Bez dat' => [null, null],
        ] as $name => [$start, $end]) {
            Season::create([
                'name' => $name,
                'organization_id' => $organization->id,
                'start_date' => $start?->toDateString(),
                'end_date' => $end?->toDateString(),
            ]);
        }

        $planned = $this->getJson(route('seasons.index', ['status' => 'planned']))->assertOk();
        $this->assertSame(['Nadchodzący'], collect($planned->json('items'))->pluck('name')->all());
        $planned->assertJsonPath('items.0.status_label', 'Zaplanowany');
        $planned->assertJsonPath('items.0.status_variant', 'planned');

        $live = $this->getJson(route('seasons.index', ['status' => 'live']))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Trwający', 'Dzisiaj'],
            collect($live->json('items'))->pluck('name')->all(),
        );

        $finished = $this->getJson(route('seasons.index', ['status' => 'finished']))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Miniony', 'Odwrócony'],
            collect($finished->json('items'))->pluck('name')->all(),
        );
        $finished->assertJsonPath('items.0.status_label', 'Zakończony');

        $all = $this->getJson(route('seasons.index', ['status' => 'nieznany']))->assertOk();
        $this->assertCount(6, $all->json('items'));
        $undated = collect($all->json('items'))->firstWhere('name', 'Bez dat');
        $this->assertNull($undated['status_label']);
        $this->assertNull($undated['status_variant']);
    }
}
