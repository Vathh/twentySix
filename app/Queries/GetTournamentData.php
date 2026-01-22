<?php

namespace App\Queries;

use App\Models\Tournament;
use App\ViewModels\TournamentDataViewModel;

class GetTournamentData
{
    public function get(int $tournamentId): TournamentDataViewModel
    {
        $tournament = Tournament::with([
            'season.league',
            'season.admins',
            'groupStandings.player',
            'games.player1',
            'games.player2',
            'games.winner',
            'playoffGames.player1',
            'playoffGames.player2',
            'playoffGames.winner',
            'achievements.player',
            'results.player'
        ])->findOrFail($tournamentId);

        return new TournamentDataViewModel($tournament);
    }
}
