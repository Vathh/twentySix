<?php

namespace App\ViewModels;

use App\Domain\GameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\PlayerDomain;
use App\Domain\SeasonDomain;
use App\Domain\TournamentDomain;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class TournamentResultsViewModel
{

    public function __construct(
        public Tournament $tournament
    )
    {
    }

    public function groupStandings(): array
    {
        $result = [];

        $standingsDomains = $this->tournament
                                ->groupStandings
                                ->map(fn($standing) => GroupStandingDomain::fromEloquent($standing, ['player']));

        foreach ($standingsDomains as $standing)
        {
            $result[$standing->groupNumber][$standing->player->id] = $standing;
        }

        return $result;
    }

    public function games(): array
    {
        $result = [];

        $gamesDomains = $this->tournament
                    ->games->map(fn($game) => GameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($gamesDomains as $game) {
            $result[$game->groupNumber][$game->player1->id][$game->player2->id] = $game;
        }

        return $result;
    }

    public function players(): array
    {
        $result = [];

        foreach ($this->tournament->groupStandings as $standing)
        {
            $result[$standing->gameNumber][] = PlayerDomain::fromEloquent($standing->player);
        }

        return $result;
    }

    public function groupNumbers(): Collection
    {
        return $this->tournament
                    ->groupStandings
                    ->map(fn($standing) => $standing->groupNumber)
                    ->unique()
                    ->sort()
                    ->collect();
    }

    public function tournament(): TournamentDomain
    {
        return TournamentDomain::fromEloquent($this->tournament);
    }

    public function season(): SeasonDomain
    {
        return SeasonDomain::fromEloquent($this->tournament->season, ['league', 'admins']);
    }
}
