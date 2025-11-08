<?php

namespace App\Services;

use App\Models\GroupStanding;
use App\Repositories\GameRepository;
use App\Repositories\GroupStandingRepository;
use Illuminate\Support\Collection;

class GroupStandingService
{

    public function __construct(
        private GameRepository          $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
    )
    {
    }

    public function updateGroupStandings(int $tournamentId, int $groupNumber): void
    {
        $finishedGames = $this->gameRepository->getFinishedGroupGames($tournamentId, $groupNumber);
        $groupStandings = $this->groupStandingRepository->getStandingsByGroupNumberAndTournamentId($tournamentId, $groupNumber);


    }

    public function sortStandings(Collection $groupStandings, Collection $finishedGames): Collection
    {
        $sorted = $groupStandings->sortByDesc(function ($standing) {
            return [$standing->points, $standing->legs_difference];
        })->values();

        $groupedByPointsAndLegsDifference = $sorted->groupBy(function ($standing) {
            return $standing->points . '-' . $standing->legs_difference;
        });

        $result = collect();

        foreach ($groupedByPointsAndLegsDifference as $group) {
            if ($group->count() === 1) {
                $standing = $group->first();
                $result->push($group->first());
                continue;
            }

            $group = $this->compareByDirectGame($group, $finishedGames);
        }
    }

    public function compareByDirectGame(Collection $standingsToCompare, Collection $finishedGames): Collection
    {
        $result = collect();

        if($standingsToCompare->count() === 2) {
            $player1Id = $standingsToCompare->first()->player->id;
            $player2Id = $standingsToCompare->last()->player->id;

            $faceToFaceGame = $finishedGames->first(function ($game) use ($player1Id, $player2Id) {

            });
        }
    }
}
