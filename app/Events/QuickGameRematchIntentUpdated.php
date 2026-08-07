<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickGameRematchIntentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, array{playerId: int, name: string}>  $intents
     */
    public function __construct(
        public int $sourceLobbyId,
        public int $hostId,
        public array $intents,
    ) {
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('quick-game-lobby.'.$this->sourceLobbyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rematch.intent.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sourceLobbyId' => $this->sourceLobbyId,
            'hostId' => $this->hostId,
            'intents' => $this->intents,
        ];
    }
}
