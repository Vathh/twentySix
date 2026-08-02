<?php

namespace App\Services\QuickGame;

use App\Models\Users\User;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\QuickGame\QuickGameFfaPresenceRepository;
use App\Repositories\QuickGame\QuickGameFfaSessionRepository;
use App\Repositories\QuickGame\QuickGameLobbyRepository;

class QuickGameLobbyAuthorizationService
{
    public function __construct(
        private QuickGameLobbyRepository $lobbyRepository,
        private PlayerRepository $playerRepository,
        private QuickGameFfaSessionRepository $ffaSessionRepository,
        private QuickGameFfaPresenceRepository $ffaPresenceRepository,
    ) {
    }

    /**
     * @return false|array{id: int}
     */
    public function canSubscribe(?User $user, int|string $lobbyId): bool|array
    {
        if ($user === null) {
            return false;
        }

        $lobbyId = filter_var($lobbyId, FILTER_VALIDATE_INT);
        if ($lobbyId === false || $lobbyId < 1) {
            return false;
        }

        $lobby = $this->lobbyRepository->findForChannelAuth($lobbyId);
        if ($lobby === null) {
            return false;
        }

        if ((int) $lobby->host_id === (int) $user->id) {
            return ['id' => $user->id];
        }

        foreach ($lobby->players as $lobbyPlayer) {
            if ($lobbyPlayer->player_id
                && $lobbyPlayer->player
                && (int) $lobbyPlayer->player->user_id === (int) $user->id) {
                return ['id' => $user->id];
            }
        }

        if ($lobby->status === 'started' && $lobby->ffa_session_id) {
            $player = $this->playerRepository->findByUserId($user->id);
            if ($player !== null) {
                $session = $this->ffaSessionRepository->findById($lobby->ffa_session_id);
                if ($session !== null) {
                    $playerIds = array_map('intval', $session->player_order ?? []);
                    if (in_array($player->id, $playerIds, true)
                        && ! $this->ffaPresenceRepository->hasLeftStatus($session->id, $player->id)) {
                        return ['id' => $user->id];
                    }
                }
            }
        }

        $player = $this->playerRepository->findByUserId($user->id);
        if ($player !== null && $lobby->invitations->contains(
            fn ($invitation) => (int) $invitation->invited_player_id === $player->id
                && $invitation->status === 'pending'
        )) {
            return ['id' => $user->id];
        }

        return false;
    }
}
