<?php

namespace App\Jobs;

use App\Services\Push\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminPushJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $tokens
     */
    public function __construct(
        public array $tokens,
        public string $title,
        public string $body,
    ) {}

    public function handle(ExpoPushService $expoPushService): void
    {
        if ($this->tokens === []) {
            return;
        }

        $expoPushService->sendToTokens($this->tokens, [
            'title' => $this->title,
            'body' => $this->body,
            'data' => [
                'type' => 'admin_broadcast',
            ],
        ]);
    }
}
