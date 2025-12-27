<?php

namespace App\ViewModels;

use App\Domain\AchievementDomain;
use App\Domain\GameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\PlayerDomain;
use App\Domain\SeasonDomain;
use App\Domain\TournamentDomain;
use App\Enums\AchievementType;
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

        $gameDomains = $this->tournament
                    ->games->map(fn($game) => GameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player1->id][$game->player2->id] = $game;
        }

        foreach ($gameDomains as $game) {
//            if($result[$game->groupNumber][$game->player2->id][$game->player1->id] !== $game) {
//                $result[]
//            }
            $result[$game->groupNumber][$game->player2->id][$game->player1->id] = $game;
        }

        return $result;
    }

    public function players(): array
    {
        $result = [];

        foreach ($this->tournament->groupStandings as $standing)
        {
            $result[$standing->group_number][] = PlayerDomain::fromEloquent($standing->player);
        }

        return $result;
    }

    public function groupNumbers(): Collection
    {
        return $this->tournament
                    ->groupStandings
                    ->map(fn($standing) => $standing->group_number)
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

    public function achievements(): Collection
    {
        $achievementDomains = $this->tournament->achievements->map(fn($achievement) => AchievementDomain::fromEloquent($achievement, ['player']))->collect();

        $result = [];

        foreach ($achievementDomains as $achievement)
        {
            switch ($achievement->type) {
                case AchievementType::ONE_SEVENTY:
                case AchievementType::MAX:
                    if(empty($result[$achievement->player->id]['player'])){
                        $result[$achievement->player->id]['player'] = $achievement->player;
                    }
                    if(empty($result[$achievement->player->id][$achievement->type->value])){
                        $result[$achievement->player->id][$achievement->type->value] = 1;
                    }else
                    {
                        $result[$achievement->player->id][$achievement->type->value]++;
                    }
                    break;
                case AchievementType::HF:
                case AchievementType::QF:
                    $result[$achievement->player->id][$achievement->type->value][] = $achievement;
                    break;
            }
        }

        $test = collect($result);

        return collect($result);
    }
}
