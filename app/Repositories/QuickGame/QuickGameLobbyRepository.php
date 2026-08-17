<?php

namespace App\Repositories\QuickGame;

use App\Models\QuickGame\QuickGameLobby;
use App\Models\QuickGame\QuickGameLobbyPlayer;
use App\Models\QuickGame\QuickGameLobbyInvitation;
use App\Models\QuickGame\QuickGameLobbyRematchIntent;
use Illuminate\Support\Facades\DB;

use App\Domain\GameScoring\MatchFormat;

class QuickGameLobbyRepository
{
    public function create(int $hostUserId): QuickGameLobby
    {
        return QuickGameLobby::create(array_merge(
            ['host_id' => $hostUserId, 'status' => 'waiting'],
            MatchFormat::default()->toDatabaseColumns(),
        ));
    }

    public function find(int $lobbyId): QuickGameLobby
    {
        return QuickGameLobby::with(['host.player', 'players.player'])
            ->findOrFail($lobbyId);
    }

    public function findForChannelAuth(int $lobbyId): ?QuickGameLobby
    {
        return QuickGameLobby::query()
            ->with(['players.player', 'invitations'])
            ->find($lobbyId);
    }

    public function addPlayer(int $lobbyId, ?int $playerId, ?string $tempPlayerName, bool $isRegistered): void
    {
        QuickGameLobbyPlayer::create([
            'lobby_id' => $lobbyId,
            'player_id' => $playerId,
            'temp_player_name' => $tempPlayerName,
            'is_registered' => $isRegistered,
            'is_ready' => false,
        ]);
    }

    public function removePlayer(int $lobbyId, ?int $playerId, ?string $tempPlayerName): void
    {
        $query = QuickGameLobbyPlayer::where('lobby_id', $lobbyId);
        if ($playerId !== null) {
            $query->where('player_id', $playerId);
        } elseif ($tempPlayerName !== null) {
            $query->where('temp_player_name', $tempPlayerName);
        } else {
            return;
        }
        $query->delete();
    }

    public function delete(int $lobbyId): void
    {
        QuickGameLobbyPlayer::where('lobby_id', $lobbyId)->delete();
        QuickGameLobby::destroy($lobbyId);
    }

    /**
     * Lobby waiting|started, w których user jest hostem lub zarejestrowanym graczem.
     *
     * @return \Illuminate\Support\Collection<int, QuickGameLobby>
     */
    public function findActiveLobbiesForUser(int $userId): \Illuminate\Support\Collection
    {
        return QuickGameLobby::query()
            ->whereIn('status', ['waiting', 'started'])
            ->where(function ($q) use ($userId) {
                $q->where('host_id', $userId)
                    ->orWhereHas('players.player', function ($pq) use ($userId) {
                        $pq->where('user_id', $userId);
                    });
            })
            ->with(['host.player', 'players.player'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return int liczba usuniętych lobby
     */
    public function pruneWaitingOlderThan(\DateTimeInterface $olderThan): int
    {
        $ids = QuickGameLobby::query()
            ->where('status', 'waiting')
            ->where('updated_at', '<', $olderThan)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->delete((int) $id);
        }

        return $ids->count();
    }

    public function rejectOtherPendingInvitationsForPlayer(int $playerId, int $exceptLobbyId): void
    {
        QuickGameLobbyInvitation::query()
            ->where('invited_player_id', $playerId)
            ->where('status', 'pending')
            ->where('lobby_id', '!=', $exceptLobbyId)
            ->update(['status' => 'rejected']);
    }

    public function setPlayerReady(int $lobbyId, int $playerId, bool $isReady): void
    {
        QuickGameLobbyPlayer::where('lobby_id', $lobbyId)
            ->where('player_id', $playerId)
            ->update(['is_ready' => $isReady]);
    }

    public function updateSettings(
        int $lobbyId,
        int $hostUserId,
        ?MatchFormat $matchFormat = null,
    ): QuickGameLobby {
        $lobby = $this->find($lobbyId);
        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może zmieniać ustawienia lobby');
        }
        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Nie można zmieniać ustawień po rozpoczęciu meczu');
        }
        if ($matchFormat !== null) {
            $matchFormat->validate();
            DB::table('quick_game_lobbies')->where('id', $lobbyId)->update(array_merge(
                $this->formatDatabaseColumns($matchFormat),
                ['updated_at' => now()],
            ));
        }

        return $this->find($lobbyId);
    }

    public function updateScoringMode(int $lobbyId, int $hostUserId, string $scoringMode): QuickGameLobby
    {
        $lobby = $this->find($lobbyId);
        if ($lobby->host_id !== $hostUserId) {
            throw new \RuntimeException('Tylko host może zmieniać ustawienia lobby');
        }
        if ($lobby->status !== 'waiting') {
            throw new \RuntimeException('Nie można zmieniać ustawień po rozpoczęciu meczu');
        }
        if (!in_array($scoringMode, ['one_device', 'each_own'], true)) {
            throw new \RuntimeException('Nieprawidłowy tryb liczenia');
        }
        DB::table('quick_game_lobbies')->where('id', $lobbyId)->update([
            'scoring_mode' => $scoringMode,
            'updated_at' => now(),
        ]);
        return $this->find($lobbyId);
    }

    public function startGame(int $lobbyId, ?MatchFormat $matchFormat = null, ?string $scoringMode = null): QuickGameLobby
    {
        $this->find($lobbyId);
        $now = now();
        $updates = [
            'status' => 'started',
            'started_at' => $now,
            'updated_at' => $now,
        ];
        if ($matchFormat !== null) {
            $matchFormat->validate();
            $updates = array_merge($updates, $this->formatDatabaseColumns($matchFormat));
        }
        if ($scoringMode !== null && in_array($scoringMode, ['one_device', 'each_own'], true)) {
            $updates['scoring_mode'] = $scoringMode;
        }
        DB::table('quick_game_lobbies')
            ->where('id', $lobbyId)
            ->update($updates);

        return $this->find($lobbyId);
    }

    public function attachFfaMeta(int $lobbyId, int $ffaSessionId, array $playerOrderLobbyPlayerIds): void
    {
        DB::table('quick_game_lobbies')->where('id', $lobbyId)->update([
            'ffa_session_id' => $ffaSessionId,
            'player_order' => json_encode(array_values(array_map('intval', $playerOrderLobbyPlayerIds))),
            'updated_at' => now(),
        ]);
    }

    public function createInvitation(int $lobbyId, int $invitedPlayerId): QuickGameLobbyInvitation
    {
        return QuickGameLobbyInvitation::create([
            'lobby_id' => $lobbyId,
            'invited_player_id' => $invitedPlayerId,
            'status' => 'pending',
        ]);
    }

    /**
     * Nowe pending albo ponowne otwarcie odrzuconego/zaakceptowanego wiersza
     * (unikalny klucz lobby_id + invited_player_id).
     */
    public function createOrReinvite(int $lobbyId, int $invitedPlayerId): QuickGameLobbyInvitation
    {
        $existing = QuickGameLobbyInvitation::query()
            ->where('lobby_id', $lobbyId)
            ->where('invited_player_id', $invitedPlayerId)
            ->first();

        if ($existing === null) {
            return $this->createInvitation($lobbyId, $invitedPlayerId);
        }

        if ($existing->status === 'pending') {
            return $existing;
        }

        $existing->update(['status' => 'pending']);

        return $existing->fresh();
    }

    public function getPendingInvitationsForPlayer(int $playerId): \Illuminate\Database\Eloquent\Collection
    {
        return QuickGameLobbyInvitation::with(['lobby.host.player'])
            ->where('invited_player_id', $playerId)
            ->where('status', 'pending')
            ->whereHas('lobby', fn ($q) => $q->where('status', 'waiting'))
            ->orderByDesc('created_at')
            ->get();
    }

    public function markInvitationAccepted(int $lobbyId, int $playerId): void
    {
        QuickGameLobbyInvitation::where('lobby_id', $lobbyId)
            ->where('invited_player_id', $playerId)
            ->where('status', 'pending')
            ->update(['status' => 'accepted']);
    }

    public function markInvitationRejected(int $invitationId, int $playerId): void
    {
        QuickGameLobbyInvitation::where('id', $invitationId)
            ->where('invited_player_id', $playerId)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }

    public function hasPendingInvitation(int $lobbyId, int $invitedPlayerId): bool
    {
        return QuickGameLobbyInvitation::where('lobby_id', $lobbyId)
            ->where('invited_player_id', $invitedPlayerId)
            ->where('status', 'pending')
            ->exists();
    }

    public function markFinished(int $lobbyId, int $quickGameId): void
    {
        DB::table('quick_game_lobbies')->where('id', $lobbyId)->update([
            'status' => 'finished',
            'quick_game_id' => $quickGameId,
            'updated_at' => now(),
        ]);
    }

    public function setRematchLobbyId(int $sourceLobbyId, int $rematchLobbyId): void
    {
        DB::table('quick_game_lobbies')->where('id', $sourceLobbyId)->update([
            'rematch_lobby_id' => $rematchLobbyId,
            'updated_at' => now(),
        ]);
    }

    public function upsertRematchIntent(int $sourceLobbyId, int $playerId): void
    {
        QuickGameLobbyRematchIntent::query()->firstOrCreate([
            'source_lobby_id' => $sourceLobbyId,
            'player_id' => $playerId,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, QuickGameLobbyRematchIntent>
     */
    public function getRematchIntents(int $sourceLobbyId)
    {
        return QuickGameLobbyRematchIntent::query()
            ->with('player')
            ->where('source_lobby_id', $sourceLobbyId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<int>
     */
    public function getRematchIntentPlayerIds(int $sourceLobbyId): array
    {
        return QuickGameLobbyRematchIntent::query()
            ->where('source_lobby_id', $sourceLobbyId)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function addPlayerReady(int $lobbyId, ?int $playerId, ?string $tempPlayerName, bool $isRegistered, bool $isReady = false): void
    {
        QuickGameLobbyPlayer::create([
            'lobby_id' => $lobbyId,
            'player_id' => $playerId,
            'temp_player_name' => $tempPlayerName,
            'is_registered' => $isRegistered,
            'is_ready' => $isReady,
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function formatDatabaseColumns(MatchFormat $format): array
    {
        $cols = $format->toDatabaseColumns();
        $cols['bob27_mode'] = $format->isBob27() ? $format->bob27Mode : null;
        $cols['bob27_bull'] = $format->isBob27() ? $format->bob27Bull : null;

        return $cols;
    }
}












