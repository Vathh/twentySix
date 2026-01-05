<?php

namespace App\Services;

use App\DTO\GameResultDTO;
use App\Factories\PlayoffBracketFactory;
use App\Repositories\GroupStandingRepository;
use App\Repositories\PlayoffGameRepository;

class PlayoffService
{

    public function __construct(
        private PlayoffBracketFactory $bracketFactory,
        private PlayoffGameRepository $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
    )
    {
    }

    /**
     * @param int $tournamentId
     * @return void
     */
    public function generateBracket(int $tournamentId): void
    {
        $playerIds = $this->groupStandingRepository
                            ->getPlayerIdsToAdvanceFromGroups($tournamentId, 2)
                            ->toArray();

        $playoffGames = $this->bracketFactory->createFor16($tournamentId, $playerIds);

        $this->gameRepository->createMany($playoffGames);
    }

    public function updateGame(GameResultDTO $dto): void
    {
        $gameToUpdate = $this->gameRepository->find($dto->gameId);

        $winnerDestinationGame = $this->gameRepository->findByTournamentIdAndSlot($dto->tournamentId, $dto->)
    }

    public function advanceWinner()
}
