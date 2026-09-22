<?php

namespace App\Services\Stats;

use App\Domain\Stats\ThreeDartAverageSet;
use App\Repositories\Game\GameVisitRepository;

class CompetitionThreeDartAverageService
{
    public function __construct(
        private GameVisitRepository $gameVisitRepository,
    ) {}

    /**
     * @param  list<int>  $groupGameIds
     * @param  list<int>  $playoffGameIds
     */
    public function forTournamentMatches(array $groupGameIds, array $playoffGameIds): ThreeDartAverageSet
    {
        return $this->fromMatchIds($groupGameIds, $playoffGameIds, []);
    }

    /**
     * @param  list<int>  $leagueGameIds
     */
    public function forLeagueMatches(array $leagueGameIds): ThreeDartAverageSet
    {
        return $this->fromMatchIds([], [], $leagueGameIds);
    }

    /**
     * @param  list<int>  $groupGameIds
     * @param  list<int>  $playoffGameIds
     * @param  list<int>  $leagueGameIds
     */
    private function fromMatchIds(array $groupGameIds, array $playoffGameIds, array $leagueGameIds): ThreeDartAverageSet
    {
        $rows = $this->gameVisitRepository->scoringTotalsForMatches(
            $groupGameIds,
            $playoffGameIds,
            $leagueGameIds,
        );

        return ThreeDartAverageSet::fromRows($rows);
    }
}
