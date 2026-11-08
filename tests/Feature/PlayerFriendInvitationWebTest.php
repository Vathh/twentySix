<?php

namespace Tests\Feature;

use App\Models\Friends\FriendshipInvitation;
use App\Models\Users\User;
use App\Repositories\Friends\FriendshipRepository;
use App\Services\Player\PlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerFriendInvitationWebTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $receiver;

    protected function setUp(): void
    {
        parent::setUp();

        $playerService = app(PlayerService::class);
        $this->sender = User::factory()->create(['email' => 'sender@test.com']);
        $this->receiver = User::factory()->create(['email' => 'receiver@test.com']);
        $playerService->create('Nadawca', $this->sender->id);
        $playerService->create('Odbiorca', $this->receiver->id);
    }

    public function test_web_invite_creates_pending_invitation_not_friendship(): void
    {
        $receiverPlayer = $this->receiver->player;

        $response = $this->actingAs($this->sender)
            ->post(route('players.add-friend', $receiverPlayer));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('friendship_invitations', [
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'status' => 'pending',
        ]);

        $this->assertFalse(
            DB::table('friendships')
                ->where(function ($query) {
                    $query->where('user_id', $this->sender->id)
                        ->where('friend_id', $this->receiver->id);
                })
                ->orWhere(function ($query) {
                    $query->where('user_id', $this->receiver->id)
                        ->where('friend_id', $this->sender->id);
                })
                ->exists()
        );
    }

    public function test_receiver_can_accept_invitation_from_web(): void
    {
        $invitation = FriendshipInvitation::create([
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('friends.invitations.accept', $invitation));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('friendship_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $this->assertTrue(
            DB::table('friendships')
                ->where('user_id', $this->sender->id)
                ->where('friend_id', $this->receiver->id)
                ->exists()
        );
    }

    public function test_a_friend_can_be_removed_from_the_profile(): void
    {
        $receiverPlayer = $this->receiver->player;
        app(FriendshipRepository::class)->addFriendship($this->sender->id, $this->receiver->id);

        $this->actingAs($this->sender)
            ->get(route('players.show', $receiverPlayer))
            ->assertOk()
            ->assertSee('Usuń znajomego');

        $this->actingAs($this->sender)
            ->delete(route('players.remove-friend', $receiverPlayer))
            ->assertRedirect()
            ->assertSessionHas('success', 'Znajomy został usunięty.');

        $this->assertDatabaseMissing('friendships', [
            'user_id' => $this->sender->id,
            'friend_id' => $this->receiver->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'user_id' => $this->sender->id,
            'subject_type' => 'user',
            'subject_key' => (string) $this->receiver->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'user_id' => $this->receiver->id,
            'subject_type' => 'user',
            'subject_key' => (string) $this->sender->id,
        ]);

        $this->actingAs($this->sender)
            ->get(route('players.show', $receiverPlayer))
            ->assertOk()
            ->assertSee('Dodaj do znajomych')
            ->assertDontSee('Usuń znajomego');
    }

    public function test_a_player_can_be_blocked_from_the_profile(): void
    {
        $receiverPlayer = $this->receiver->player;

        $this->actingAs($this->sender)
            ->get(route('players.show', $receiverPlayer))
            ->assertOk()
            ->assertSee('Zablokuj')
            ->assertDontSee('Odblokuj');

        $this->actingAs($this->sender)
            ->post(route('blocks.store'), ['userId' => $this->receiver->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Gracz został zablokowany.');

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $this->sender->id,
            'blocked_id' => $this->receiver->id,
        ]);

        $this->actingAs($this->sender)
            ->get(route('players.show', $receiverPlayer))
            ->assertOk()
            ->assertSee('Odblokuj');
    }
}
