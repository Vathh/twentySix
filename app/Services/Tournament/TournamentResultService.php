<?php

namespace App\Services\Tournament;

use App\Enums\GameStage;
use App\Factories\TournamentResultsFactory;
use App\Repositories\PointScheme\PointSchemeRepository;
use App\Repositories\PointScheme\PointSchemeRuleRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Repositories\Tournament\TournamentResultRepository;
use App\Services\GroupStanding\GroupStandingService;
use Illuminate\Support\Collection;

class TournamentResultService
{

    public function __construct(
        private GroupStandingService       $groupStandingService,
        private TournamentResultsFactory   $factory,
        private TournamentRepository       $tournamentRepository,
        private TournamentResultRepository $resultRepository,
        private PointSchemeRuleRepository  $pointSchemeRuleRepository,
    )
    {
    }

    /**
     * @param int $tournamentId
     * @return void
     */
    public function createForGroupLosers(int $tournamentId): void
    {
        $standings = $this->groupStandingService->getLosersGroupStandings($tournamentId);

        $tournament = $this->tournamentRepository->findWithSeasonAndPointSchemeRules($tournamentId);

        $results = $this->factory->createManyForGroup($standings, $tournament);

        $this->resultRepository->createMany($results->toArray());
    }

    /**
     * @param int $tournamentId
     * @param int $playerId
     * @param GameStage $stage
     * @param int|null $place
     * @return void
     */
    public function createForPlayoff(int $tournamentId, int $playerId, GameStage $stage, ?int $place): void
    {
        $tournament = $this->tournamentRepository->findWithSeasonAndPointScheme($tournamentId);

        if ($tournament->pointScheme === null) {
            throw new \RuntimeException("Tournament {$tournamentId} does not have a point scheme assigned");
        }

        if ($tournament->season === null) {
            throw new \RuntimeException("Tournament {$tournamentId} does not have a season assigned");
        }

        $rule = $this->pointSchemeRuleRepository->find($tournament->pointScheme->id, $stage, $place);

        $result = $this->factory->createForPlayoff($tournament->season->id,
                                                    $tournament->id,
                                                    $playerId,
                                                    $rule->points,
                                                    $rule->place,
                                                    $stage);

        $this->resultRepository->create($result);
    }
}











