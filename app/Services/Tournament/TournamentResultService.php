<?php

namespace App\Services\Tournament;

use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameStage;
use App\Factories\TournamentResultsFactory;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Repositories\Tournament\TournamentResultRepository;
use App\Services\GroupStanding\GroupStandingService;

class TournamentResultService
{
    public function __construct(
        private GroupStandingService $groupStandingService,
        private TournamentResultsFactory $factory,
        private TournamentRepository $tournamentRepository,
        private TournamentResultRepository $resultRepository,
        private TournamentOverallPlaceService $overallPlaceService,
        private PlayerRepository $playerRepository,
    ) {}

    public function createForGroupLosers(int $tournamentId): void
    {
        $tournament = $this->tournamentRepository->findWithSeasonAndPointSchemeRules($tournamentId);
        $standings = $this->groupStandingService->getLosersGroupStandings($tournamentId);

        if ($standings->isEmpty()) {
            return;
        }

        $this->resultRepository->createMany($this->factory->createManyForGroup($standings, $tournament)->toArray());

        $this->overallPlaceService->recalculateOverallPlaces($tournamentId);
    }

    public function createForPlayoff(int $tournamentId, int $playerId, GameStage $stage, ?int $place): void
    {
        if ($this->playerRepository->isByePlayerId($playerId)) {
            return;
        }
        $tournament = $this->tournamentRepository->findWithSeasonAndPointScheme($tournamentId);

        $seasonId = $this->tracksSeasonPoints($tournament) ? $tournament->season->id : null;

        $this->resultRepository->create($this->factory->createForPlayoff(
            $seasonId,
            $tournament->id,
            $playerId,
            null,
            $place,
            $stage,
        ));

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
        if ($this->playerRepository->isByePlayerId($playerId)) {
            return;
        }
        $tournament = $this->tournamentRepository->findWithSeasonAndPointScheme($tournamentId);

        $seasonId = $this->tracksSeasonPoints($tournament) ? $tournament->season->id : null;

        $this->resultRepository->upsertForPlayer(
            seasonId: $seasonId,
            tournamentId: $tournamentId,
            playerId: $playerId,
            points: null,
            place: $place,
            stage: $stage,
        );

        $this->overallPlaceService->recalculateOverallPlaces($tournamentId);
    }

    private function tracksSeasonPoints(TournamentDomain $tournament): bool
    {
        return $tournament->season !== null && $tournament->pointScheme !== null;
    }
}
