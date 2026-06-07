<?php

namespace App\Repositories\Tournament;

use App\Domain\PlayerDomain;
use App\Models\Player\Player;
use App\Models\Tournament\TournamentGuestParticipant;
use Illuminate\Support\Collection;

class TournamentGuestParticipantRepository
{
    /**
     * @return Collection<int, PlayerDomain>
     */
    public function getPlayersForTournament(int $tournamentId): Collection
    {
        return TournamentGuestParticipant::where('tournament_id', $tournamentId)
            ->with('player')
            ->get()
            ->map(fn (TournamentGuestParticipant $entry) => PlayerDomain::fromEloquent($entry->player))
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function getPlayerIdsForTournament(int $tournamentId): Collection
    {
        return TournamentGuestParticipant::where('tournament_id', $tournamentId)
            ->pluck('player_id');
    }

    public function isInTournament(int $tournamentId, int $playerId): bool
    {
        return TournamentGuestParticipant::where('tournament_id', $tournamentId)
            ->where('player_id', $playerId)
            ->exists();
    }

    public function add(int $tournamentId, int $playerId): void
    {
        if ($this->isInTournament($tournamentId, $playerId)) {
            throw new \RuntimeException('Gość jest już dodany do tego turnieju');
        }

        $player = Player::findOrFail($playerId);

        if ($player->user_id !== null) {
            throw new \RuntimeException('Można dodać tylko gościa bez konta');
        }

        TournamentGuestParticipant::create([
            'tournament_id' => $tournamentId,
            'player_id' => $playerId,
        ]);
    }

    public function remove(int $tournamentId, int $playerId): void
    {
        $deleted = TournamentGuestParticipant::where('tournament_id', $tournamentId)
            ->where('player_id', $playerId)
            ->delete();

        if ($deleted === 0) {
            throw new \RuntimeException('Gość nie bierze udziału w tym turnieju');
        }
    }
}
