<?php

namespace App\Services;

use App\DTO\ActiveGameDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStage;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Repositories\GameRepository;
use App\Repositories\PlayoffGameRepository;
use App\Repositories\TournamentRepository;
use App\Services\Tournament\TournamentResultService;
use App\Services\MatchLegService;
use App\Services\QuickGameService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class GameService
{

    public function __construct(
        private GameRepository       $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private GroupStandingService $groupStandingService,
        private AchievementsService  $achievementsService,
        private PlayoffService       $playoffService,
        private TournamentRepository $tournamentRepository,
        private TournamentResultService  $tournamentResultService,
        private MatchLegService      $matchLegService,
        private QuickGameService    $quickGameService,
    )
    {
    }

    public function setStatusInProgress(int $gameId): void
    {
        $this->gameRepository->setStatusInProgress($gameId);
    }

    public function update(UpdateGameDTO $dto): bool
    {
        if($dto->gameResultDTO->type === GameType::PLAYOFF)
        {
            return $this->handlePlayoffGameUpdate($dto);
        }else if($dto->gameResultDTO->type === GameType::GROUP)
        {
            return $this->handleGroupGameUpdate($dto);
        }else if($dto->gameResultDTO->type === GameType::QUICK_MATCH)
        {
            return $this->quickGameService->updateQuickGame($dto);
        }

        return false;
    }

    /**
     * @param int $tournamentId
     * @return Collection<ActiveGameDTO>
     */
    public function getActiveGames(int $tournamentId): Collection
    {
        try {
            DB::transaction(function () use ($tournamentId) {
                $games = $this->gameRepository->getActive($tournamentId);
                $playoffGames = $this->playoffGameRepository->getActive($tournamentId);

            return collect($games->map(fn($game) => ActiveGameDTO::fromGame($game)))
                    ->merge($playoffGames->map(fn($game) => ActiveGameDTO::fromPlayoffGame($game)));
            });

        } catch (Throwable $e) {
            return collect();
        }

        return collect();
    }

    private function handleTournamentResultCreating(int $winnerId,
                                                   int $player1Id,
                                                   int $player2Id,
                                                   int $tournamentId,
                                                   GameStage $stage,
                                                   ?int $winnerPlace): void
    {
        if($winnerPlace === null) {
            switch($winnerId){
                case $player1Id:
                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player2Id,
                        $stage,
                        null);
                    break;
                case $player2Id:
                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player1Id,
                        $stage,
                        null);
                    break;
            }
        } else {
            switch($winnerId){
                case $player1Id:
                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player1Id,
                        $stage,
                        $winnerPlace);

                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player2Id,
                        $stage,
                        $winnerPlace + 1);
                    break;
                case $player2Id:
                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player2Id,
                        $stage,
                        $winnerPlace);

                    $this->tournamentResultService->createForPlayoff($tournamentId,
                        $player1Id,
                        $stage,
                        $winnerPlace + 1);
                    break;
            }
        }
    }

    private function handlePlayoffGameUpdate(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $gameToUpdate = $this->playoffGameRepository->find($dto->gameResultDTO->gameId);
                $gameToUpdate->checkUpdateDataAccuracy($dto->gameResultDTO);

                if(!in_array($gameToUpdate->round, [GameStage::FINAL, GameStage::THIRD, GameStage::SEMI]))
                {
                    $this->handleTournamentResultCreating($dto->gameResultDTO->winnerId,
                                                            $dto->gameResultDTO->player1Id,
                                                            $dto->gameResultDTO->player2Id,
                                                            $gameToUpdate->tournamentId,
                                                            $gameToUpdate->round,
                                                            null);
                } else if ($gameToUpdate->round === GameStage::THIRD)
                {
                    $this->handleTournamentResultCreating($dto->gameResultDTO->winnerId,
                                                            $dto->gameResultDTO->player1Id,
                                                            $dto->gameResultDTO->player2Id,
                                                            $gameToUpdate->tournamentId,
                                                            GameStage::THIRD,
                                                            3);

                    $this->tournamentRepository->finishTournament($gameToUpdate->tournamentId);
                } else if ($gameToUpdate->round === GameStage::FINAL)
                {
                    $this->handleTournamentResultCreating($dto->gameResultDTO->winnerId,
                                                            $dto->gameResultDTO->player1Id,
                                                            $dto->gameResultDTO->player2Id,
                                                            $gameToUpdate->tournamentId,
                                                            GameStage::FINAL,
                                                            1);
                    $this->tournamentRepository->finishTournament($gameToUpdate->tournamentId);
                }

                $this->playoffService->update($dto->gameResultDTO, $gameToUpdate);

                $this->achievementsService->createMany($dto->achievementsDTOs);

                // Zapisz szczegóły legów jeśli są dostępne
                if (!empty($dto->legsDTOs)) {
                    $this->matchLegService->createMany(
                        $dto->legsDTOs,
                        gameId: null,
                        playoffGameId: $gameToUpdate->id
                    );
                }

            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function handleGroupGameUpdate(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $gameToUpdate = $this->gameRepository->find($dto->gameResultDTO->gameId);
                $gameToUpdate->checkUpdateDataAccuracy($dto->gameResultDTO->player1Id,
                                                        $dto->gameResultDTO->player2Id,
                                                        $dto->gameResultDTO->winnerId);

                $this->groupStandingService->updateStandingsDetails($dto->gameResultDTO);
                $this->gameRepository->finish($dto->gameResultDTO);
                $this->achievementsService->createMany($dto->achievementsDTOs);
                $this->groupStandingService->updateGroupStandings($dto->gameResultDTO->tournamentId,
                                                                    $dto->gameResultDTO->groupNumber);

                // Zapisz szczegóły legów jeśli są dostępne
                if (!empty($dto->legsDTOs)) {
                    $this->matchLegService->createMany(
                        $dto->legsDTOs,
                        gameId: $dto->gameResultDTO->gameId,
                        playoffGameId: null
                    );
                }

                $this->handlePlayoffStart($dto->gameResultDTO->tournamentId);

            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function handlePlayoffStart(int $tournamentId): void
    {
        if($this->gameRepository->checkIfPlayoffShouldBeStarted($tournamentId))
        {
            $this->tournamentResultService->createForGroupLosers($tournamentId);
            $this->playoffService->generateBracket($tournamentId);
            try {
                $this->tournamentRepository->changeStatus($tournamentId,
                    TournamentStatus::PLAYOFF);
            } catch (Throwable $e) {

            }
        }
    }
}
