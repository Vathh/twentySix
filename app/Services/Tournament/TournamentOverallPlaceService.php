<?php

namespace App\Services\Tournament;

use App\Enums\GameStage;
use App\Models\GroupStanding\GroupStanding;
use App\Models\PlayoffGame\PlayoffGame;
use App\Models\Tournament\TournamentResult;
use App\Repositories\Tournament\TournamentRepository;
use App\Support\Tournament\TournamentOverallPlaceCalculator;

class TournamentOverallPlaceService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
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

        $results = TournamentResult::query()
            ->where('tournament_id', $tournamentId)
            ->get();

        if ($results->isEmpty()) {
            return;
        }

        $groupPlacesByPlayer = GroupStanding::query()
            ->where('tournament_id', $tournamentId)
            ->pluck('place', 'player_id');

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
            TournamentResult::query()
                ->where('tournament_id', $tournamentId)
                ->where('player_id', $playerId)
                ->update(['place' => $place]);
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
        $countsByStage = PlayoffGame::query()
            ->where('tournament_id', $tournamentId)
            ->get()
            ->countBy(fn (PlayoffGame $game) => $game->round->value);

        foreach ([GameStage::SIXTEEN, GameStage::EIGHT, GameStage::QUARTER] as $stage) {
            $count = $countsByStage[$stage->value] ?? 0;

            if ($count > 0) {
                return $count * 2;
            }
        }

        return null;
    }
}
