<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private User $user3;

    protected function setUp(): void
    {
        parent::setUp();

        // Utwórz testowych użytkowników z graczami
        $this->user1 = User::factory()->create(['email' => 'user1@test.com']);
        $this->user2 = User::factory()->create(['email' => 'user2@test.com']);
        $this->user3 = User::factory()->create(['email' => 'user3@test.com']);

        $playerService = app(PlayerService::class);
        $playerService->create('Tomek', $this->user1->id);
        $playerService->create('Radek', $this->user2->id);
        $playerService->create('Jan', $this->user3->id);
    }

    public function test_user_can_add_friend(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/add', [
            'friendId' => $this->user2->id
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Znajomy został dodany'
                 ]);
    }

    public function test_user_cannot_add_self_as_friend(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/add', [
            'friendId' => $this->user1->id
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'message' => 'Nie możesz dodać siebie jako znajomego'
                 ]);
    }

    public function test_user_can_get_friends_list(): void
    {
        Sanctum::actingAs($this->user1);

        // Dodaj znajomego
        $this->postJson('/api/friends/add', [
            'friendId' => $this->user2->id
        ]);

        $response = $this->getJson('/api/friends');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'friends' => [
                         '*' => ['id', 'name', 'playerId']
                     ]
                 ])
                 ->assertJsonCount(1, 'friends');
    }

    public function test_user_can_remove_friend(): void
    {
        Sanctum::actingAs($this->user1);

        // Dodaj znajomego
        $this->postJson('/api/friends/add', [
            'friendId' => $this->user2->id
        ]);

        // Usuń znajomego
        $response = $this->deleteJson('/api/friends/remove', [
            'friendId' => $this->user2->id
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Znajomy został usunięty'
                 ]);

        // Sprawdź czy lista jest pusta
        $friendsResponse = $this->getJson('/api/friends');
        $friendsResponse->assertJsonCount(0, 'friends');
    }

    public function test_user_can_search_users_by_name(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/users/search?q=Rad');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'users' => [
                         '*' => ['id', 'email', 'name', 'playerId']
                     ]
                 ])
                 ->assertJsonCount(1, 'users')
                 ->assertJsonPath('users.0.name', 'Radek');
    }

    public function test_search_excludes_current_user(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/users/search?q=Tom');

        // Powinien znaleźć tylko innych użytkowników o nazwie zawierającej "Tom"
        // (nie powinien znaleźć siebie)
        $response->assertStatus(200);
        
        $users = $response->json('users');
        $userIds = collect($users)->pluck('id');
        $this->assertNotContains($this->user1->id, $userIds);
    }
}
