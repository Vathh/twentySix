<?php

namespace App\Events;

use App\Support\GameScoring\GameScoringContext;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameScoringCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameScoringContext $context,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel($this->context->broadcastChannelName()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'gameId' => $this->context->gameId,
            'kind' => $this->context->kind->value,
            'cancelled' => true,
        ];
    }
}
