<?php

namespace App\Jobs;

use App\Enums\InvitationPushType;
use App\Repositories\Push\UserPushTokenRepository;
use App\Services\Push\ExpoPushService;
use App\Services\Push\InvitationPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvitationPushJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $context
     */
    public function __construct(
        public int $recipientUserId,
        public string $type,
        public int $invitationId,
        public array $context = [],
    ) {
    }

    public function handle(
        UserPushTokenRepository $tokenRepository,
        InvitationPushService $invitationPushService,
        ExpoPushService $expoPushService,
    ): void {
        $pushType = InvitationPushType::tryFrom($this->type);
        if ($pushType === null) {
            return;
        }

        $tokens = $tokenRepository->getTokensForUser($this->recipientUserId)->all();
        if ($tokens === []) {
            return;
        }

        $message = $invitationPushService->buildMessage(
            $pushType,
            $this->invitationId,
            $this->context,
        );

        $expoPushService->sendToTokens($tokens, $message);
    }
}
