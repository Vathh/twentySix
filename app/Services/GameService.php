<?php

namespace App\Services;

use App\DTO\ActiveGameDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameType;
use App\Repositories\GameRepository;
use App\Repositories\PlayoffGameRepository;
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

        }else if($dto->gameResultDTO->type === GameType::GROUP)
        {
            try {
                DB::transaction(function () use ($dto) {
                    $this->groupStandingService->updateStandingsDetails($dto->gameResultDTO);
                    $this->gameRepository->finish($dto->gameResultDTO);
                    $this->achievementsService->createMany($dto->achievementsDTOs);
                    $this->groupStandingService->updateGroupStandings($dto->gameResultDTO->tournamentId, $dto->gameResultDTO->groupNumber);

                    if($this->gameRepository->checkIfPlayoffShouldBeStarted($dto->gameResultDTO->tournamentId))
                    {
                        $this->playoffService->generateBracket($dto->gameResultDTO->tournamentId);
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
