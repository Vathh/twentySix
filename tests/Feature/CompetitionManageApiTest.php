<?php

namespace Tests\Feature;

use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionManageApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name, bool $canCreate = false): User
    {
        $user = User::factory()->create([
            'can_create_organizations' => $canCreate,
        ]);
        app(PlayerService::class)->create($name, $user->id);

        return $user->fresh();
    }

    public function test_user_without_flag_cannot_create_an_organization(): void
    {
        Sanctum::actingAs($this->makeUser('Obcy'));

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonPath('canCreate', false);

        $this->postJson('/api/organizations', [
            'name' => 'Cudza',
            'description' => '',
        ])->assertForbidden();
    }

    public function test_admin_manages_organization_season_and_tournament(): void
    {
        $owner = $this->makeUser('Właściciel', true);
        $other = $this->makeUser('Drugi');
        Sanctum::actingAs($owner);

        $this->getJson('/api/organizations')->assertJsonPath('canCreate', true);

        $orgId = $this->postJson('/api/organizations', [
            'name' => 'Klub Test',
            'description' => 'Opis',
        ])->assertCreated()->json('id');

        $this->putJson('/api/organizations/'.$orgId, [
            'name' => 'Klub Test 2',
            'description' => 'Nowy opis',
        ])->assertOk()->assertJsonPath('organization.name', 'Klub Test 2');

        $this->postJson('/api/organizations/'.$orgId.'/admins/remove', [
            'userId' => $owner->id,
        ])->assertStatus(422);

        $this->postJson('/api/organizations/'.$orgId.'/admins', [
            'userId' => $other->id,
        ])->assertOk()->assertJsonPath('canRemoveAdmin', true);

        $this->postJson('/api/organizations/'.$orgId.'/guests', [
            'name' => 'Gość',
        ])->assertOk()->assertJsonPath('guests.0.name', 'Gość');

        $outsider = $this->makeUser('Poza');
        Sanctum::actingAs($outsider);
        $this->getJson('/api/organizations/'.$orgId.'/manage')->assertForbidden();
        Sanctum::actingAs($owner);

        $seasonId = $this->postJson('/api/organizations/'.$orgId.'/seasons', [
            'name' => 'Sezon A',
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
        ])->assertCreated()->json('id');

        $this->putJson('/api/seasons/'.$seasonId, [
            'name' => 'Sezon B',
            'startDate' => '2026-02-01',
            'endDate' => '2026-11-30',
        ])->assertOk()->assertJsonPath('season.name', 'Sezon B');

        $tournamentId = $this->postJson('/api/organizations/'.$orgId.'/tournaments', [
            'name' => 'Puchar',
            'date' => '2026-06-01',
        ])->assertCreated()->json('id');

        $admins = $this->postJson('/api/tournaments/'.$tournamentId.'/admins', [
            'userId' => $other->id,
        ])->assertOk()->json('admins');
        $this->assertNotEmpty($admins);

        while (count($admins) > 1) {
            $this->postJson('/api/tournaments/'.$tournamentId.'/admins/remove', [
                'userId' => $admins[0]['id'],
            ])->assertOk();
            array_shift($admins);
        }

        $this->postJson('/api/tournaments/'.$tournamentId.'/admins/remove', [
            'userId' => $admins[0]['id'],
        ])->assertStatus(422);

        $this->deleteJson('/api/tournaments/'.$tournamentId, [
            'delete_password' => 'password',
            'entity_name_confirmation' => 'Puchar',
        ])->assertOk();

        $this->deleteJson('/api/seasons/'.$seasonId, [
            'delete_password' => 'password',
            'entity_name_confirmation' => 'Sezon B',
        ])->assertOk();

        $this->deleteJson('/api/organizations/'.$orgId, [
            'delete_password' => 'password',
            'entity_name_confirmation' => 'Klub Test 2',
        ])->assertOk();

        $this->getJson('/api/organizations/'.$orgId)->assertNotFound();
    }
}
