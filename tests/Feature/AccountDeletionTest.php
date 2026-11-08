<?php

namespace Tests\Feature;

use App\Models\Activity\Activity;
use App\Models\Activity\ActivityComment;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use App\Repositories\Organization\OrganizationRepository;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_deletion_anonymizes_the_player_and_keeps_other_replies(): void
    {
        Storage::fake('public');

        $user = $this->account('adam@example.com', 'Adam');
        $other = $this->account('ola@example.com', 'Ola');
        $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        $player->forceFill([
            'description' => 'Opis',
            'avatar_path' => 'avatars/adam.jpg',
        ])->save();
        Storage::disk('public')->put('avatars/adam.jpg', 'jpeg');

        $activity = Activity::query()->create([
            'type' => 'note',
            'actor_player_id' => $player->id,
            'actor_user_id' => $user->id,
            'subject_type' => 'player',
            'subject_id' => $player->id,
            'payload' => [],
            'occurred_at' => now(),
        ]);
        $comment = ActivityComment::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'body' => 'Mój komentarz',
        ]);
        $reply = ActivityComment::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $other->id,
            'parent_id' => $comment->id,
            'body' => 'Zostaję',
        ]);
        app(FriendshipRepository::class)->addFriendship($user->id, $other->id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/account', ['password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('message', 'Konto zostało usunięte.');

        $this->assertDatabaseMissing('users', ['email' => 'adam@example.com']);
        $this->assertDatabaseMissing('friendships', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('activity_comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('activity_comments', [
            'id' => $reply->id,
            'body' => 'Zostaję',
            'parent_id' => null,
        ]);

        $player->refresh();
        $this->assertNull($player->user_id);
        $this->assertSame('Gracz usunięty', $player->name);
        $this->assertNull($player->description);
        $this->assertNull($player->avatar_path);
        Storage::disk('public')->assertMissing('avatars/adam.jpg');

        $this->postJson('/api/register', [
            'name' => 'Adam Drugi',
            'email' => 'adam@example.com',
            'password' => 'password123',
            'acceptedTerms' => true,
        ])->assertCreated();
    }

    public function test_wrong_password_keeps_the_account(): void
    {
        $user = $this->account('adam@example.com');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/account', ['password' => 'zle-haslo'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_sole_organization_admin_cannot_delete_the_account(): void
    {
        $user = $this->account('admin@example.com');
        app(OrganizationRepository::class)->create('Apagon', null, $user->id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/account', ['password' => 'password123'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jesteś jedynym administratorem organizacji „Apagon”. Wskaż innego administratora, zanim usuniesz konto.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('players', [
            'user_id' => $user->id,
            'name' => 'Adam',
        ]);
    }

    public function test_public_page_deletes_the_account_and_rejects_a_bad_password(): void
    {
        $user = $this->account('adam@example.com');

        $this->get(route('pages.account.delete'))
            ->assertOk()
            ->assertSee('Usuń konto');

        $this->from(route('pages.account.delete'))
            ->post(route('account.delete'), [
                'email' => 'adam@example.com',
                'password' => 'zle-haslo',
            ])
            ->assertRedirect(route('pages.account.delete'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->from(route('pages.account.delete'))
            ->post(route('account.delete'), [
                'email' => 'adam@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('pages.account.delete'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => 'adam@example.com']);
        $this->assertDatabaseHas('players', [
            'name' => 'Gracz usunięty',
            'user_id' => null,
        ]);
    }

    private function account(string $email, string $name = 'Adam'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);
        app(PlayerService::class)->create($name, $user->id);

        return $user;
    }
}
