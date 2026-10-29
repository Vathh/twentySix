<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Models\Organization\Organization;
use App\Models\PointScheme\PointScheme;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentManageApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        $user = User::factory()->create();
        app(PlayerService::class)->create($name, $user->id);

        return $user->fresh();
    }

    public function test_outsider_cannot_open_setup_or_create_tournament(): void
    {
        $admin = $this->makeUser('Admin');
        $outsider = $this->makeUser('Obcy');
        $org = Organization::create(['name' => 'Klub', 'description' => '']);
        $org->admins()->attach($admin->id);

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/organizations/'.$org->id.'/tournaments', [
            'name' => 'Wieczór',
            'date' => '2026-10-29',
        ])->assertCreated();

        Sanctum::actingAs($outsider);
        $this->postJson('/api/organizations/'.$org->id.'/tournaments', [
            'name' => 'Cudzy',
            'date' => '2026-10-29',
        ])->assertForbidden();
        $this->getJson('/api/tournaments/'.$created->json('id').'/setup')->assertForbidden();
    }

    public function test_admin_adds_guests_starts_and_sees_tablet_code(): void
    {
        PointScheme::create([
            'name' => 'od 2 do 8',
            'min_players' => 2,
            'max_players' => 8,
        ]);

        $admin = $this->makeUser('Prowadzący');
        $org = Organization::create(['name' => 'Sala', 'description' => '']);
        $org->admins()->attach($admin->id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/organizations/'.$org->id)
            ->assertOk()
            ->assertJsonPath('canManage', true);

        $created = $this->postJson('/api/organizations/'.$org->id.'/tournaments', [
            'name' => 'Czwartkowy',
            'date' => '2026-10-29',
        ])->assertCreated();
        $id = $created->json('id');

        $this->postJson('/api/tournaments/'.$id.'/start', [
            'tournamentFormat' => 'single_elimination',
            'grandFinalMode' => 'reset',
        ])->assertStatus(422);

        foreach (['Ania', 'Bartek', 'Celina', 'Darek'] as $name) {
            $this->postJson('/api/tournaments/'.$id.'/guests', ['name' => $name])->assertOk();
        }

        $setup = $this->getJson('/api/tournaments/'.$id.'/setup')
            ->assertOk()
            ->assertJsonPath('participantCount', 4)
            ->assertJsonPath('minPlayers', 4)
            ->assertJsonPath('tournament.canManageParticipants', true);
        $this->assertNotEmpty($setup->json('joinCode'));

        $started = $this->postJson('/api/tournaments/'.$id.'/start', [
            'tournamentFormat' => 'single_elimination',
        ])->assertOk()
            ->assertJsonPath('started', true);
        $code = $started->json('tabletLogin.code');
        $this->assertNotEmpty($code);

        $this->getJson('/api/tournaments/'.$id)
            ->assertOk()
            ->assertJsonPath('canManage', true)
            ->assertJsonPath('tournament.status', TournamentStatus::PLAYOFF->value)
            ->assertJsonPath('tabletLogin.code', $code);

        $rotated = $this->postJson('/api/tournaments/'.$id.'/tablet-code')->assertOk();
        $this->assertNotSame($code, $rotated->json('code'));
    }
}
