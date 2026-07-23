<?php

namespace Tests\Feature;

use App\Enums\InvitationPushType;
use App\Jobs\SendInvitationPushJob;
use App\Models\Users\User;
use App\Services\Friends\FriendshipService;
use App\Services\Player\PlayerService;
use App\Services\Push\ExpoPushService;
use App\Services\Push\InvitationPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FriendshipPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_invitation_dispatches_push_job(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $playerService = app(PlayerService::class);
        $playerService->create('Sender', $sender->id);
        $playerService->create('Receiver', $receiver->id);

        $invitation = app(FriendshipService::class)->sendInvitation($sender->id, $receiver->id);

        Queue::assertPushed(SendInvitationPushJob::class, function (SendInvitationPushJob $job) use ($receiver, $invitation) {
            return $job->recipientUserId === $receiver->id
                && $job->type === InvitationPushType::Friend->value
                && $job->invitationId === $invitation->id
                && ($job->context['senderName'] ?? null) === 'Sender';
        });
    }

    public function test_expo_push_service_sends_expected_payload(): void
    {
        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ticket-1'],
                ],
            ]),
        ]);

        $message = app(InvitationPushService::class)->buildMessage(
            InvitationPushType::Friend,
            42,
            ['senderName' => 'Tomek'],
        );

        app(ExpoPushService::class)->sendToTokens(
            ['ExponentPushToken[test]'],
            $message,
        );

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $first = $payload[0] ?? null;

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && ($first['to'] ?? null) === 'ExponentPushToken[test]'
                && ($first['title'] ?? null) === 'twentySix'
                && ($first['body'] ?? null) === 'Tomek zaprasza Cię do znajomych'
                && ($first['data']['type'] ?? null) === 'friend_invitation'
                && ($first['data']['tab'] ?? null) === 'friends'
                && ($first['channelId'] ?? null) === 'invitations';
        });
    }
}
