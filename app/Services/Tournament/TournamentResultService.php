<?php

namespace App\Services\Tournament;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStage;
use App\Factories\TournamentResultsFactory;
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
        private TournamentOverallPlaceService $overallPlaceService,
    )
    {
    }

    /**
     * @param int $tournamentId
     * @return void
     */
    public function createForGroupLosers(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findWithSeasonAndPointSchemeRules($tournamentId);
        $standings = $this->groupStandingService->getLosersGroupStandings($tournamentId);

        if ($standings->isEmpty()) {
            return;
        }

        $results = $this->tracksLeaguePoints($tournament)
            ? $this->factory->createManyForGroup($standings, $tournament)
            : $this->factory->createManyForGroupWithoutPoints($standings, $tournament);

        $this->resultRepository->createMany($results->toArray());

        $this->overallPlaceService->recalculateOverallPlaces($tournamentId);
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

        if ($this->tracksLeaguePoints($tournament)) {
            $rule = $this->pointSchemeRuleRepository->find($tournament->pointScheme->id, $stage, $place);

            $result = $this->factory->createForPlayoff(
                $tournament->season->id,
                $tournament->id,
                $playerId,
                $rule->points,
                $rule->place,
                $stage,
            );
        } else {
            $result = $this->factory->createForPlayoff(
                null,
                $tournament->id,
                $playerId,
                null,
                $place,
                $stage,
            );
        }

        $this->resultRepository->create($result);

        $this->overallPlaceService->recalculateOverallPlaces($tournamentId);
    }

    /**
     * Aktualizuje miejsca i punkty podium (finał 1–2 lub mecz o 3. miejsce 3–4).
     */
    public function syncPodium(
        int $tournamentId,
        int $winnerId,
        int $player1Id,
        int $player2Id,
        GameStage $stage,
        int $winnerPlace,
    ): void {
        $loserId = $winnerId === $player1Id ? $player2Id : $player1Id;

        $this->upsertPodiumPlace($tournamentId, $winnerId, $stage, $winnerPlace);
        $this->upsertPodiumPlace($tournamentId, $loserId, $stage, $winnerPlace + 1);
    }

    public function syncFinalPodium(int $tournamentId, int $winnerId, int $player1Id, int $player2Id): void
    {
        $this->syncPodium($tournamentId, $winnerId, $player1Id, $player2Id, GameStage::FINAL, 1);
    }

    public function syncThirdPodium(int $tournamentId, int $winnerId, int $player1Id, int $player2Id): void
    {
        $this->syncPodium($tournamentId, $winnerId, $player1Id, $player2Id, GameStage::THIRD, 3);
    }

    public function clearPodiumStage(int $tournamentId, GameStage $stage): void
    {
        $this->resultRepository->clearPodiumStage($tournamentId, $stage);
    }

    private function upsertPodiumPlace(int $tournamentId, int $playerId, GameStage $stage, int $place): void
    {
        $tournament = $this->tournamentRepository->findWithSeasonAndPointScheme($tournamentId);

        if ($this->tracksLeaguePoints($tournament)) {
            $rule = $this->pointSchemeRuleRepository->find($tournament->pointScheme->id, $stage, $place);

            $this->resultRepository->upsertForPlayer(
                seasonId: $tournament->season->id,
                tournamentId: $tournamentId,
                playerId: $playerId,
                points: $rule->points,
                place: $rule->place,
                stage: $stage,
            );

            $this->overallPlaceService->recalculateOverallPlaces($tournamentId);

            return;
        }

        $this->resultRepository->upsertForPlayer(
            seasonId: null,
            tournamentId: $tournamentId,
            playerId: $playerId,
            points: null,
            place: $place,
            stage: $stage,
        );

        $this->overallPlaceService->recalculateOverallPlaces($tournamentId);
    }

    private function tracksLeaguePoints(TournamentDomain $tournament): bool
    {
        return $tournament->season !== null && $tournament->pointScheme !== null;
    }
}
