<?php

namespace App\Services\Tournament;

use App\Enums\GameStage;
use App\Models\Tournament\TournamentResult;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Repositories\Tournament\TournamentResultRepository;
use App\Support\Tournament\TournamentOverallPlaceCalculator;

class TournamentOverallPlaceService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentResultRepository $tournamentResultRepository,
        private GroupStandingRepository $groupStandingRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private TournamentOverallPlaceCalculator $calculator,
    ) {
    }

    public function recalculateOverallPlaces(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findWithSeasonAndPointScheme($tournamentId);

        $bracketSize = $this->resolveBracketSize($tournamentId, $tournament->playoffBracketSize);

        if ($bracketSize === null) {
            return;
        }

        $results = $this->tournamentResultRepository->getAllForTournament($tournamentId);

        if ($results->isEmpty()) {
            return;
        }

        $groupPlacesByPlayer = $this->groupStandingRepository->getPlacesByPlayerId($tournamentId);

        $rows = $results->map(fn (TournamentResult $result) => [
            'player_id' => $result->player_id,
            'elimination_stage' => $result->elimination_stage,
            'group_place' => $result->elimination_stage === GameStage::GROUP
                ? ($groupPlacesByPlayer[$result->player_id] ?? null)
                : null,
            'current_place' => $result->place,
        ]);

        $places = $this->calculator->calculate($bracketSize, $rows);

        foreach ($places as $playerId => $place) {
            $this->tournamentResultRepository->updatePlace($tournamentId, $playerId, $place);
        }
    }

    private function resolveBracketSize(int $tournamentId, ?int $configuredSize): ?int
    {
        if ($configuredSize !== null) {
            return $configuredSize;
        }

        return $this->inferBracketSizeFromPlayoffGames($tournamentId);
    }

    private function inferBracketSizeFromPlayoffGames(int $tournamentId): ?int
    {
        $countsByStage = $this->playoffGameRepository->countByRoundForTournament($tournamentId);

        foreach ([GameStage::SIXTEEN, GameStage::EIGHT, GameStage::QUARTER] as $stage) {
            $count = $countsByStage[$stage->value] ?? 0;

            if ($count > 0) {
                return $count * 2;
            }
        }

        return null;
    }
}
