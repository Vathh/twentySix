<?php

namespace App\Events;

use App\Models\QuickGame\QuickGameLobby;
use App\Support\QuickGameLobbyPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickGameRematchCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sourceLobbyId,
        public QuickGameLobby $lobby,
    ) {
        $this->lobby->loadMissing(['host.player', 'players.player']);
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
        return 'rematch.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sourceLobbyId' => $this->sourceLobbyId,
            'lobby' => QuickGameLobbyPayload::fromLobby($this->lobby),
        ];
    }
}
