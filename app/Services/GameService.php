<?php

namespace App\Services;

use App\DTO\ActiveGameDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Repositories\GameRepository;
use App\Repositories\PlayoffGameRepository;
use App\Repositories\TournamentRepository;
use App\Services\Tournament\TournamentResultService;
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
            try {
                DB::transaction(function () use ($dto) {
                    $gameToUpdate = $this->playoffGameRepository->find($dto->gameResultDTO->gameId);
                    $gameToUpdate->checkUpdateDataAccuracy($dto->gameResultDTO->player1Id, $dto->gameResultDTO->player2Id, $dto->gameResultDTO->winnerId);

                    $this->playoffService->update($dto->gameResultDTO, $gameToUpdate);

                    $this->achievementsService->createMany($dto->achievementsDTOs);

                });

                return true;
            } catch (Throwable $e) {
                return false;
            }
        }else if($dto->gameResultDTO->type === GameType::GROUP)
        {
            try {
                DB::transaction(function () use ($dto) {
                    $gameToUpdate = $this->gameRepository->find($dto->gameResultDTO->gameId);
                    $gameToUpdate->checkUpdateDataAccuracy($dto->gameResultDTO->player1Id, $dto->gameResultDTO->player2Id, $dto->gameResultDTO->winnerId);

                    $this->groupStandingService->updateStandingsDetails($dto->gameResultDTO);
                    $this->gameRepository->finish($dto->gameResultDTO);
                    $this->achievementsService->createMany($dto->achievementsDTOs);
                    $this->groupStandingService->updateGroupStandings($dto->gameResultDTO->tournamentId, $dto->gameResultDTO->groupNumber);

                    if($this->gameRepository->checkIfPlayoffShouldBeStarted($dto->gameResultDTO->tournamentId))
                    {
                        $this->tournamentResultService->createForGroupLosers($dto->gameResultDTO->tournamentId);
                        $this->playoffService->generateBracket($dto->gameResultDTO->tournamentId);
                        $this->tournamentRepository->changeStatus($dto->gameResultDTO->tournamentId, TournamentStatus::PLAYOFF);
                    }
                });

                return true;
            } catch (Throwable $e) {
                return false;
            }
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
}
