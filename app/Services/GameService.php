<?php

namespace App\Services;

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Repositories\GameRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class GameService
{

    public function __construct(
        private GameRepository       $gameRepository,
        private GroupStandingService $groupStandingService,
        private AchievementsService  $achievementsService
    )
    {
    }

    public function setStatusInProgress(int $gameId): void
    {
        $this->gameRepository->setStatusInProgress($gameId);
    }

    public function update(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $this->groupStandingService->updateStandingsDetails($dto->gameResultDTO);
                $this->gameRepository->update($dto->gameResultDTO);
                $this->achievementsService->createMany($dto->achievementsDTOs);
                $this->groupStandingService->updateGroupStandings($dto->gameResultDTO->tournamentId, $dto->gameResultDTO->groupNumber);
            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getActiveGames(int $tournamentId): Collection
    {
        return $this->gameRepository->getActiveGames($tournamentId);
    }
}
