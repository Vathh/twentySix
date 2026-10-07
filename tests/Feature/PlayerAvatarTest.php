<?php

namespace Tests\Feature;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->owner = User::factory()->create();
        app(PlayerService::class)->create('Ala Nowak', $this->owner->id);
        $this->player = $this->owner->fresh()->player;
    }

    public function test_owner_can_upload_a_square_jpeg_avatar(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->post('/api/players/'.$this->player->id.'/avatar', [
            'avatar' => UploadedFile::fake()->image('wide.png', 800, 200),
        ]);

        $response->assertOk();
        $url = $response->json('avatarUrl');
        $this->assertIsString($url);
        $this->assertStringContainsString('/storage/avatars/'.$this->player->id.'.jpg', $url);

        $path = 'avatars/'.$this->player->id.'.jpg';
        Storage::disk('public')->assertExists($path);

        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);

        $this->player->refresh();
        $this->assertSame($path, $this->player->avatar_path);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/players/'.$this->player->id)
            ->assertOk()
            ->assertJsonPath('player.avatarUrl', $this->player->avatarUrl());
    }

    public function test_other_user_cannot_upload_an_avatar(): void
    {
        $other = User::factory()->create();
        app(PlayerService::class)->create('Inny', $other->id);

        Sanctum::actingAs($other);

        $this->post('/api/players/'.$this->player->id.'/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertForbidden();
    }

    public function test_guest_player_cannot_receive_an_avatar(): void
    {
        $guest = Player::create(['name' => 'Gość']);

        Sanctum::actingAs($this->owner);

        $this->post('/api/players/'.$guest->id.'/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertForbidden();
    }

    public function test_upload_rejects_a_file_that_is_too_large_or_not_an_image(): void
    {
        Sanctum::actingAs($this->owner);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/players/'.$this->player->id.'/avatar', [
                'avatar' => UploadedFile::fake()->image('big.jpg', 40, 40)->size(3000),
            ])->assertStatus(422);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/players/'.$this->player->id.'/avatar', [
                'avatar' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
            ])->assertStatus(422);

        $this->assertNull($this->player->fresh()->avatar_path);
    }

    public function test_owner_can_remove_an_avatar(): void
    {
        Sanctum::actingAs($this->owner);

        $this->post('/api/players/'.$this->player->id.'/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ])->assertOk();

        $this->delete('/api/players/'.$this->player->id.'/avatar')
            ->assertOk()
            ->assertJsonPath('avatarUrl', null);

        Storage::disk('public')->assertMissing('avatars/'.$this->player->id.'.jpg');
        $this->assertNull($this->player->fresh()->avatar_path);
    }

    public function test_web_profile_update_stores_and_removes_the_avatar(): void
    {
        $this->actingAs($this->owner)
            ->get(route('players.edit', $this->player))
            ->assertOk()
            ->assertSee('Wybierz zdjęcie', false)
            ->assertSee('playerAvatarCrop', false);

        $this->actingAs($this->owner)
            ->put(route('players.update', $this->player), [
                'description' => 'Lubię 501.',
                'avatar' => UploadedFile::fake()->image('face.jpg', 600, 400),
            ])
            ->assertRedirect(route('players.show', $this->player));

        $this->player->refresh();
        $this->assertSame('Lubię 501.', $this->player->description);
        Storage::disk('public')->assertExists('avatars/'.$this->player->id.'.jpg');

        $this->actingAs($this->owner)
            ->get(route('players.show', $this->player))
            ->assertOk()
            ->assertSee('player-avatar', false);

        $this->actingAs($this->owner)
            ->get(route('friends.panel'))
            ->assertOk();

        $this->actingAs($this->owner)
            ->get(route('invitations.index'))
            ->assertOk();

        $this->actingAs($this->owner)
            ->put(route('players.update', $this->player), [
                'description' => 'Lubię 501.',
                'remove_avatar' => '1',
            ])
            ->assertRedirect(route('players.show', $this->player));

        Storage::disk('public')->assertMissing('avatars/'.$this->player->id.'.jpg');
        $this->assertNull($this->player->fresh()->avatar_path);
    }

    public function test_friends_list_includes_avatar_url(): void
    {
        $friend = User::factory()->create();
        app(PlayerService::class)->create('Bartek', $friend->id);
        $friendPlayer = $friend->fresh()->player;
        $friendPlayer->forceFill([
            'avatar_path' => 'avatars/'.$friendPlayer->id.'.jpg',
            'updated_at' => now(),
        ])->save();

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/friends/add', ['friendId' => $friend->id])->assertCreated();

        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonPath('friends.0.playerId', $friendPlayer->id)
            ->assertJsonPath('friends.0.avatarUrl', $friendPlayer->fresh()->avatarUrl());
    }
}
