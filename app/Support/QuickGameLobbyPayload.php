<?php

namespace App\Support;

use App\Models\QuickGame\QuickGameLobby;
use App\Services\QuickGame\QuickGameLobbyService;

class QuickGameLobbyPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromLobby(QuickGameLobby $lobby, ?int $currentUserId = null): array
    {
        $lobby->loadMissing(['host.player', 'players.player']);

        $orderIds = is_array($lobby->player_order) ? $lobby->player_order : null;
        $lobbyPlayers = QuickGameLobbyPlayerOrder::sort($lobby->players, $orderIds);

        $players = $lobbyPlayers->map(function ($p) use ($lobby) {
            $isRegistered = $p->player_id !== null;
            $isHost = $p->player && (int) $p->player->user_id === (int) $lobby->host_id;

            return [
                'id' => $p->id,
                'playerId' => $p->player_id,
                'name' => $p->player?->name ?? null,
                'tempName' => $p->temp_player_name,
                'ready' => (bool) $p->is_ready,
                'isRegistered' => $isRegistered,
                'isHost' => $isHost,
            ];
        })->values()->all();

        $out = [
            'id' => $lobby->id,
            'hostId' => $lobby->host_id,
            'status' => $lobby->status,
            'legsCount' => $lobby->legs_count ?? QuickGameLobbyService::DEFAULT_LEGS_TO_WIN,
            'gameType' => $lobby->game_type ?? '501',
            'scoringMode' => $lobby->scoring_mode ?? 'each_own',
            'players' => $players,
        ];

        if ($lobby->status === 'started' && $lobby->quick_game_id) {
            $out['quickGameId'] = $lobby->quick_game_id;
        }

        if ($currentUserId !== null) {
            $out['youAreHost'] = (int) $lobby->host_id === (int) $currentUserId;
            if ($lobby->status === 'started') {
                $myIndex = self::resolveMyPlayerIndex($lobbyPlayers, $currentUserId);
                if ($myIndex !== null) {
                    $out['myPlayerIndex'] = $myIndex;
                }
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $orderedPlayers
     */
    private static function resolveMyPlayerIndex($orderedPlayers, int $currentUserId): ?int
    {
        foreach ($orderedPlayers as $i => $lp) {
            if ($lp->player_id && $lp->player && (int) $lp->player->user_id === $currentUserId) {
                return $i;
            }
        }

        return null;
    }
}
