<?php

namespace App\Support;

use App\Domain\GameScoring\MatchFormat;
use App\Models\QuickGame\QuickGameLobby;

class QuickGameLobbyPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromLobby(QuickGameLobby $lobby, ?int $currentUserId = null): array
    {
        $lobby->loadMissing(['host.player', 'players.player', 'ffaSession', 'invitations.invitedPlayer']);

        $orderIds = is_array($lobby->player_order) ? $lobby->player_order : null;
        $lobbyPlayers = QuickGameLobbyPlayerOrder::sort($lobby->players, $orderIds);

        $players = $lobbyPlayers->map(function ($p) use ($lobby) {
            $isRegistered = (bool) $p->is_registered;
            $isHost = $p->player && (int) $p->player->user_id === (int) $lobby->host_id;

            return [
                'id' => $p->id,
                'playerId' => $p->player_id,
                'name' => $p->player?->name ?? $p->temp_player_name,
                'tempName' => $p->temp_player_name,
                'ready' => (bool) $p->is_ready,
                'isRegistered' => $isRegistered,
                'isHost' => $isHost,
            ];
        })->values()->all();

        $format = MatchFormat::fromRecord($lobby);

        $out = [
            'id' => $lobby->id,
            'hostId' => $lobby->host_id,
            'status' => $lobby->status,
            'matchFormat' => $format->toArray(),
            'gameType' => MatchFormat::normalizeGameType($lobby->game_type ?? 'x01'),
            'scoringMode' => $lobby->scoring_mode ?? 'each_own',
            'players' => $players,
            'pendingInvites' => $lobby->invitations
                ->where('status', 'pending')
                ->map(fn ($inv) => [
                    'id' => (int) $inv->invited_player_id,
                    'name' => $inv->invitedPlayer?->name ?? 'Gracz',
                    'status' => 'sent',
                ])
                ->values()
                ->all(),
            'matchInProgress' => $lobby->status === 'started'
                && $lobby->ffaSession?->isInProgress() === true,
        ];

        if ($out['matchInProgress'] && $lobby->ffa_session_id) {
            $out['ffaSessionId'] = $lobby->ffa_session_id;
        }

        if ($currentUserId !== null) {
            $out['youAreHost'] = (int) $lobby->host_id === (int) $currentUserId;
            if ($lobby->status === 'started' && $out['matchInProgress']) {
                $myIndex = self::resolveMyPlayerIndex($lobbyPlayers, $currentUserId);
                if ($myIndex !== null) {
                    $out['myPlayerIndex'] = $myIndex;
                }
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuickGame\QuickGameLobbyPlayer>  $orderedPlayers
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
