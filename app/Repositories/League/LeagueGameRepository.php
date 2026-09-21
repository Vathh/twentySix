<?php

namespace App\Repositories\League;

use App\Enums\LeagueGameStatus;
use App\Enums\LeagueSeasonStatus;
use App\Enums\LeagueWalkoverType;
use App\Models\League\LeagueGame;
use Illuminate\Support\Collection;

class LeagueGameRepository
{
    public function findForPlay(int $gameId): LeagueGame
    {
        return LeagueGame::query()
            ->with([
                'player1',
                'player2',
                'matchday',
                'seasonDivision',
                'season.league.organization',
            ])
            ->findOrFail($gameId);
    }

    public function save(LeagueGame $game): void
    {
        $game->save();
    }

    public function resetToScheduled(LeagueGame $game): void
    {
        $game->status = LeagueGameStatus::SCHEDULED;
        $game->player1_score = 0;
        $game->player2_score = 0;
        $game->winner_id = null;
        $game->walkover_type = LeagueWalkoverType::NONE;
        $game->lobby_host_player_id = null;
        $game->opponent_accepted_at = null;
        $game->scoring_host_player_id = null;
        $this->save($game);
    }

    /**
     * @return Collection<int, LeagueGame>
     */
    public function mineForPlayer(int $playerId): Collection
    {
        return LeagueGame::query()
            ->with([
                'player1',
                'player2',
                'matchday',
                'seasonDivision',
                'season.league',
            ])
            ->where(function ($query) use ($playerId) {
                $query->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->whereHas('season', function ($query) {
                $query->whereIn('status', [
                    LeagueSeasonStatus::IN_PROGRESS->value,
                    LeagueSeasonStatus::PLAYOFFS->value,
                ]);
            })
            ->where('status', '!=', LeagueGameStatus::VOIDED->value)
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'lobby' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END")
            ->orderBy('deadline_at')
            ->get();
    }

    /**
     * @return Collection<int, LeagueGame>
     */
    public function pendingInvitationsForPlayer(int $playerId): Collection
    {
        return LeagueGame::query()
            ->with([
                'player1',
                'player2',
                'matchday',
                'seasonDivision',
                'season.league',
            ])
            ->where('status', LeagueGameStatus::LOBBY)
            ->whereNull('opponent_accepted_at')
            ->where(function ($query) use ($playerId) {
                $query->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('lobby_host_player_id', '!=', $playerId)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function hasActivePlaySession(int $playerId, ?int $exceptGameId = null): bool
    {
        $query = LeagueGame::query()
            ->where(function ($inner) use ($playerId) {
                $inner->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->whereIn('status', [LeagueGameStatus::LOBBY->value, LeagueGameStatus::IN_PROGRESS->value]);

        if ($exceptGameId !== null) {
            $query->whereKeyNot($exceptGameId);
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, LeagueGame>
     */
    public function listFinished(): Collection
    {
        return LeagueGame::query()
            ->where('status', LeagueGameStatus::FINISHED)
            ->orderBy('id')
            ->get();
    }
}
