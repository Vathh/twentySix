<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Models\Organization\Organization;
use App\Models\Season\Season;
use App\Models\Tournament\Tournament;
use App\Models\Users\User;
use App\Services\Organization\OrganizationService;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_remove_a_square_logo_on_the_edit_form(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['can_create_organizations' => true]);
        app(PlayerService::class)->create('Admin', $admin->id);
        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $admin->id);
        $model = Organization::query()->findOrFail($organization->id);

        $formats = [];
        foreach (GameStage::cases() as $stage) {
            $formats[$stage->value] = [
                'startingScore' => 501,
                'legsToWinSet' => 2,
                'setsToWinMatch' => 1,
            ];
        }

        $this->actingAs($admin)
            ->post(route('organizations.update', $model), [
                '_method' => 'PUT',
                'organizationName' => 'Apagon',
                'description' => 'Klub',
                'matchFormats' => $formats,
                'logo' => UploadedFile::fake()->image('wide.png', 800, 200),
            ])
            ->assertRedirect(route('organizations.show', $model));

        $model->refresh();
        $this->assertSame('organization-logos/'.$model->id.'.jpg', $model->logo_path);
        Storage::disk('public')->assertExists($model->logo_path);

        $this->actingAs($admin)
            ->get(route('organizations.edit', $model))
            ->assertOk()
            ->assertSee('Wybierz logo', false);

        $this->actingAs($admin)
            ->get(route('organizations.show', $model))
            ->assertOk()
            ->assertSee('/storage/organization-logos/'.$model->id.'.jpg', false);

        $index = $this->getJson(route('organizations.index'))->assertOk();
        $card = collect($index->json('items'))->firstWhere('name', 'Apagon');
        $this->assertIsArray($card);
        $this->assertStringContainsString('/storage/organization-logos/'.$model->id.'.jpg', $card['logoUrl']);
        $this->assertSame('AP', $card['initials']);

        $this->actingAs($admin)
            ->post(route('organizations.update', $model), [
                '_method' => 'PUT',
                'organizationName' => 'Apagon',
                'description' => 'Klub',
                'matchFormats' => $formats,
                'remove_logo' => '1',
            ])
            ->assertRedirect(route('organizations.show', $model));

        $this->assertNull($model->fresh()->logo_path);
        Storage::disk('public')->assertMissing('organization-logos/'.$model->id.'.jpg');
    }

    public function test_season_and_tournament_cards_show_the_organization_logo(): void
    {
        $organization = Organization::create([
            'name' => 'Apagon',
            'description' => '',
        ]);
        $organization->update([
            'logo_path' => 'organization-logos/'.$organization->id.'.jpg',
        ]);
        Season::create([
            'name' => 'Sezon letni',
            'organization_id' => $organization->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $season = Season::query()->where('name', 'Sezon letni')->firstOrFail();
        Tournament::create([
            'name' => 'Puchar sezonu',
            'season_id' => $season->id,
            'date' => '2026-06-01',
        ]);
        Tournament::create([
            'name' => 'Turniej jednorazowy',
            'organization_id' => $organization->id,
            'date' => '2026-07-01',
        ]);

        $seasonCard = collect($this->getJson(route('seasons.index'))->assertOk()->json('items'))
            ->firstWhere('name', 'Sezon letni');
        $this->assertIsArray($seasonCard);
        $this->assertStringContainsString('/storage/organization-logos/'.$organization->id.'.jpg', $seasonCard['logoUrl']);
        $this->assertSame('AP', $seasonCard['initials']);

        $cards = collect($this->getJson(route('tournaments.index'))->assertOk()->json('items'));
        foreach (['Puchar sezonu', 'Turniej jednorazowy'] as $name) {
            $card = $cards->firstWhere('name', $name);
            $this->assertIsArray($card);
            $this->assertStringContainsString('/storage/organization-logos/'.$organization->id.'.jpg', $card['logoUrl']);
            $this->assertSame('AP', $card['initials']);
        }
    }

    public function test_member_cannot_upload_a_logo(): void
    {
        $admin = User::factory()->create(['can_create_organizations' => true]);
        $member = User::factory()->create();
        app(PlayerService::class)->create('Admin', $admin->id);
        app(PlayerService::class)->create('Widz', $member->id);
        $organization = app(OrganizationService::class)->create('Apagon', 'Klub', $admin->id);

        $this->actingAs($member)
            ->post(route('organizations.update', $organization->id), [
                '_method' => 'PUT',
                'organizationName' => 'Apagon',
                'logo' => UploadedFile::fake()->image('logo.jpg'),
            ])
            ->assertForbidden();
    }
}
