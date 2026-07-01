<?php

namespace Tests\Feature;

use App\Models\Friends\FriendshipInvitation;
use App\Models\Users\User;
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
}
