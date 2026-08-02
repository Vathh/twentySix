<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickGameFfaStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public int $lobbyId,
        public array $state,
    ) {
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Uczestnicy (mobile) — prywatny kanał z auth.
            new PrivateChannel('quick-game-lobby.'.$this->lobbyId),
            // Publiczny podgląd live na webie (jak H2H group-game.*).
            new Channel('quick-game-ffa-lobby.'.$this->lobbyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ffa.state.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'state' => $this->state,
        ];
    }
}
