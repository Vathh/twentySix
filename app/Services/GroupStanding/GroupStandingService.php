<?php

namespace App\Services\GroupStanding;

use App\Domain\Game\GroupGameDomain;
use App\Domain\GroupStandingDomain;
use App\DTO\GameResultDTO;
use App\Repositories\Game\GameRepository;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Tournament\TournamentRepository;
use Illuminate\Support\Collection;

class GroupStandingService
{

    public function __construct(
        private GameRepository          $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
        private TournamentRepository    $tournamentRepository,
    )
    {
    }

    public function updateGroupStandings(int $tournamentId, int $groupNumber): void
    {
        $finishedGames = $this->gameRepository->getFinishedGroupGames($tournamentId, $groupNumber);
        $groupStandings = $this->groupStandingRepository->getByGroupNumberAndTournamentId($groupNumber, $tournamentId);

        $sortedStandings = $this->sortStandings($groupStandings, $finishedGames);

        $this->groupStandingRepository->updatePlaces($sortedStandings);
    }


    /**
     * @param GameResultDTO $dto
     * @return void
     */
    public function updateStandingsDetails(GameResultDTO $dto): void
    {
        $this->groupStandingRepository->updateDetails(
            playerId: $dto->player1Id,
            hasWon: $dto->player1Id === $dto->winnerId,
            legsWon: $dto->player1Score,
            legsLost: $dto->player2Score,
            tournamentId: $dto->tournamentId,
        );

        $this->groupStandingRepository->updateDetails(
            playerId: $dto->player2Id,
            hasWon: $dto->player2Id === $dto->winnerId,
            legsWon: $dto->player2Score,
            legsLost: $dto->player1Score,
            tournamentId: $dto->tournamentId,
        );
    }

    /**
     * @param Collection<int, GroupStandingDomain> $groupStandings
     * @param Collection<int, \App\Domain\Game\GroupGameDomain> $finishedGames
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
     * @param Collection<int, GroupGameDomain> $finishedGames
     * @return Collection<int, GroupStandingDomain>
     */
    public function compareByDirectGame(Collection $standingsToCompare, Collection $finishedGames): Collection
    {
        $playerIds = $standingsToCompare->pluck('player.id')->toArray();

        $directGames = $finishedGames->filter(function ($game) use ($playerIds) {
            return in_array($game->player1->id, $playerIds)
                && in_array($game->player2->id, $playerIds);
        })->values();

        if($directGames->count() === 0) {
            return $standingsToCompare
                    ->shuffle()
                    ->values();
        }

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

        $groups = $playerWinsMap->mapToGroups(fn($wins, $playerId) => [$wins => $playerId])->sortKeysDesc();

        $result = collect();

        foreach ($groups as $playersWithSameWins) {

            $subset = $standingsToCompare->filter(
                fn($standing) => $playersWithSameWins->contains($standing->player->id)
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

    public function getLosersGroupStandings(int $tournamentId): Collection
    {
        $advancePerGroup = $this->tournamentRepository->getAdvancePerGroup($tournamentId);

        return $this->groupStandingRepository->getGroupLosers($tournamentId, $advancePerGroup);
    }
}












