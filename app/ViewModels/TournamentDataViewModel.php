<?php

namespace App\ViewModels;

use App\Domain\AchievementDomain;
use App\Domain\Game\GroupGameDomain;
use App\Domain\Game\PlayoffGameDomain;
use App\Domain\GroupStandingDomain;
use App\Domain\PlayerDomain;
use App\Domain\SeasonDomain;
use App\Domain\Tournament\TournamentDomain;
use App\Domain\Tournament\TournamentResultDomain;
use App\Enums\AchievementType;
use App\Models\Tournament\Tournament;
use Illuminate\Support\Collection;

class TournamentDataViewModel
{

    public function __construct(
        public Tournament $tournament
    )
    {
    }

    /**
     * @return array<GroupStandingDomain>
     */
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

    /**
     * @return array<GroupGameDomain>
     */
    public function games(): array
    {
        $result = [];

        $gameDomains = $this->tournament
                    ->games->map(fn($game) => GroupGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player1->id][$game->player2->id] = $game;
        }

        foreach ($gameDomains as $game) {
            $result[$game->groupNumber][$game->player2->id][$game->player1->id] = $game;
        }

        return $result;
    }

    public function playoffGames(): array
    {
        $result = [];

        $playoffGameDomains = $this->tournament
                                    ->playoffGames
                                    ->map(fn($game) => PlayoffGameDomain::fromEloquent($game, ['player1', 'player2', 'winner']));

        foreach ($playoffGameDomains as $game) {
            $result[$game->round->value][] = $game;
        }

        return $result;
    }

    /**
     * @return array<PlayerDomain>
     */
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

    /**
     * @return \App\Domain\Tournament\TournamentDomain
     */
    public function tournament(): TournamentDomain
    {
        return TournamentDomain::fromEloquent($this->tournament);
    }

    /**
     * @return SeasonDomain
     */
    public function season(): SeasonDomain
    {
        return SeasonDomain::fromEloquent($this->tournament->season, ['league', 'admins']);
    }

    /**
     * @return Collection<AchievementDomain>
     */
    public function achievements(): Collection
    {
        $achievementDomains = $this->tournament
                                    ->achievements
                                    ->map(fn($achievement) => AchievementDomain::fromEloquent($achievement, ['player']));

        $result = [];

        foreach ($achievementDomains as $achievement) {
            if ($achievement->player === null) {
                continue;
            }

            $playerId = $achievement->player->id;

            if (! isset($result[$playerId]['player'])) {
                $result[$playerId]['player'] = $achievement->player;
                $result[$playerId]['max'] = 0;
                $result[$playerId]['one_seventy'] = 0;
                $result[$playerId]['qf'] = [];
                $result[$playerId]['hf'] = [];
            }

            switch ($achievement->type) {
                case AchievementType::ONE_SEVENTY:
                case AchievementType::MAX:
                    $result[$playerId][$achievement->type->value]++;
                    break;
                case AchievementType::HF:
                case AchievementType::QF:
                    $result[$playerId][$achievement->type->value][] = $achievement;
                    break;
            }
        }

        return collect($result);
    }

    public function results(): Collection
    {
        $resultDomains = $this->tournament
                                ->results
                                ->map(fn($result) => TournamentResultDomain::fromEloquent($result, ['player']))
                                ->sortByDesc('points');

        $array = [];

        foreach ($resultDomains as $result)
        {
            $array[$result->player->id]['player'] = $result->player;
            $array[$result->player->id]['place'] = $result->place;
            $array[$result->player->id]['points'] = $result->points;
            $array[$result->player->id]['stage'] = $result->eliminationStage;
        }

        return collect($array);
    }
}

