<?php

namespace App\Services\Tournament;

use App\Factories\TournamentResultsFactory;
use App\Repositories\PointSchemeRepository;
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
    )
    {
    }

    public function createForGroupLosers(int $tournamentId): void
    {
        $standings = $this->groupStandingService->getLosersGroupStandings($tournamentId);

        $tournament = $this->tournamentRepository->findByIdWithSeasonAndPointScheme($tournamentId);

        $this->factory->createManyForGroup($tournament->pointScheme->rules, $tournament);

        $this->resultRepository->createMany($standings->toArray());
    }

    public function createForPlayoff(int $tournamentId,)
    {
        $tournament = $this->tournamentRepository->findByIdWithSeasonAndPointScheme($tournamentId);


    }
}
