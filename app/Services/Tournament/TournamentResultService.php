<?php

namespace App\Services\Tournament;

use App\Enums\GameStage;
use App\Factories\TournamentResultsFactory;
use App\Repositories\PointSchemeRepository;
use App\Repositories\PointSchemeRuleRepository;
use App\Repositories\TournamentRepository;
use App\Repositories\TournamentResultRepository;
use App\Services\GroupStandingService;
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

        $results = $this->factory->createManyForGroup($tournament->pointScheme->rules, $tournament);

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
