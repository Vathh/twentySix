<?php

namespace App\Services;

use App\Domain\GameDomain;
use App\Domain\GroupStandingDomain;
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
        $groupStandings = $this->groupStandingRepository->getStandingsByGroupNumberAndTournamentId($groupNumber, $tournamentId);

        $sortedStandings = $this->sortStandings($groupStandings, $finishedGames);

        $this->groupStandingRepository->updateStandings($sortedStandings);
    }


    /**
     * @param Collection<int, GroupStandingDomain> $groupStandings
     * @param Collection<int, GameDomain> $finishedGames
     * @return Collection<int, GroupStandingDomain>
     */
    public function sortStandings(Collection $groupStandings, Collection $finishedGames): Collection
    {
        $sortedStandings = $groupStandings->sortByDesc(function ($standing) {
                                                return [$standing->points, $standing->legsDifference];
                                            })->values()
                                            ->map(fn ($standing, $index) => $standing->withPlace($index + 1));

        $sortedStandingsGroupedByPointsAndLegsDifference = $sortedStandings->groupBy(function ($standing) {
            return $standing->points . '-' . $standing->legsDifference;
        });

        $result = collect();

        $index = 1;

        foreach ($sortedStandingsGroupedByPointsAndLegsDifference as $group) {
            if ($group->count() === 1) {
                $result->push($group->first()->withPlace($index));
                $index++;
                continue;
            }

            $sortedGroupStandings = $this->compareByDirectGame($group, $finishedGames);

            foreach ($sortedGroupStandings as $standing) {
                $result->push($standing);
                $index++;
            }
        }

        return $result->values()
                      ->map(fn($standing, $i) => $standing->withPlace($i + 1));
    }


    /**
     * @param Collection<int, GroupStandingDomain> $standingsToCompare
     * @param Collection<int, GameDomain> $finishedGames
     * @return Collection<int, GroupStandingDomain>
     */
    public function compareByDirectGame(Collection $standingsToCompare, Collection $finishedGames): Collection
    {
        $playerIds = $standingsToCompare->pluck('player.id')->toArray();

        $directGames = $finishedGames->filter(function ($game) use ($playerIds) {
            return in_array($game->player1->id, $playerIds)
                && in_array($game->player2->id, $playerIds);
        })->values();

        $playerWinsMap = $standingsToCompare->mapWithKeys(function ($standing) use ($directGames) {
            $playerId = $standing->player->id;

            $count = $directGames
                ->where('winner.id', $playerId)
                ->count();

            return [$playerId => $count];
        });

        if ($playerWinsMap->unique()->count() === 1) {

            return $standingsToCompare
                ->shuffle()
                ->values();
        }

        $groups = $playerWinsMap->groupBy(fn ($v) => $v)->sortKeysDesc();

        $result = collect();

        foreach ($groups as $winCount => $playersWithSameWins) {

            $subset = $standingsToCompare->filter(
                fn($standing) => $playersWithSameWins->keys()->contains($standing->player->id)
            );

            if ($playersWithSameWins->count() === 1) {
                $result->push($subset->first());
            }
            else {
                $resolved = $this->compareByDirectGame(
                                        $subset,
                                        $directGames
                                    );

                $result = $result->merge($resolved);
            }
        }

        return $result->values();
    }
}
