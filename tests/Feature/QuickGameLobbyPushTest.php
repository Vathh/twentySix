<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Jobs\SendInvitationPushJob;
use App\Models\Player\Player;
use App\Models\Users\User;
use App\Services\Player\PlayerService;
use App\Services\QuickGame\QuickGameLobbyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuickGameLobbyPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_lobby_invite_dispatches_push_job(): void
    {
        Queue::fake();

        $host = User::factory()->create();
        $friend = User::factory()->create();

        $playerService = app(PlayerService::class);
        $playerService->create('Host', $host->id);
        $playerService->create('Friend', $friend->id);

        $friendPlayer = Player::where('user_id', $friend->id)->firstOrFail();

        Sanctum::actingAs($host);
        $this->postJson('/api/friends/add', ['friendId' => $friend->id])->assertCreated();

        $lobby = app(QuickGameLobbyService::class)->create($host->id);
        app(QuickGameLobbyService::class)->invite($lobby->id, $host->id, $friendPlayer->id);

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($friend) {
            return $job->recipientUserId === $friend->id
                && $job->type === InvitationPushType::Lobby->value
                && ($job->context['hostName'] ?? null) === 'Host';
        });
    }
}
