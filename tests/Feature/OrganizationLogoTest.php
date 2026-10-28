<?php

namespace Tests\Feature;

use App\Enums\GameStage;
use App\Models\Organization\Organization;
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
