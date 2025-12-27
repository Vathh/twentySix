<?php

namespace App\Queries;

use App\Models\Tournament;
use App\ViewModels\TournamentResultsViewModel;

class GetTournamentGroupResults
{
    public function get(int $tournamentId): TournamentResultsViewModel
    {
        $tournament = Tournament::with([
            'season.league',
            'season.admins',
            'groupStandings.player',
            'games.player1',
            'games.player2',
            'games.winner',
            'achievements.player'
        ])->findOrFail($tournamentId);

        return new TournamentResultsViewModel($tournament);
    }
}
