<?php

namespace App\Services\PlayoffGame;

use App\Domain\Game\PlayoffGameDomain;
use App\DTO\GameResultDTO;
use App\Enums\PlayerSlot;
use App\Enums\PlayoffSlot;
use App\Factories\PlayoffBracketFactory;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;

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
                            ->values()
                            ->toArray();

        $playoffGames = $this->bracketFactory->createFor16($tournamentId, $playerIds);

        $this->gameRepository->createMany($playoffGames);
    }

    public function update(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        $this->gameRepository->finish($dto);

        if($gameToUpdate->slot !== PlayoffSlot::THIRD
            && $gameToUpdate->slot !== PlayoffSlot::FINAL
            && $gameToUpdate->winnerDestinationSlot !== null
        ){
            $winnerDestination = $gameToUpdate->winnerDestinationSlot->toDestination();

            $this->advancePlayer($gameToUpdate->tournamentId,
                                    $winnerDestination->playoffSlot,
                                    $dto->winnerId,
                                    $winnerDestination->playerSlot);

            if ($winnerDestination->playoffSlot === PlayoffSlot::FINAL)
            {
                $loserId = $dto->winnerId === $dto->player1Id ? $dto->player2Id : $dto->player1Id;

                $this->advancePlayer($gameToUpdate->tournamentId,
                                        PlayoffSlot::THIRD,
                                        $loserId,
                                        $winnerDestination->playerSlot);
            }
        }
    }

    public function advancePlayer(int $tournamentId, PlayoffSlot $playoffSlot, int $winnerId, PlayerSlot $playerSlot): void
    {
        switch ($playerSlot){
            case PlayerSlot::A: $this->gameRepository->setPlayer1Slot($tournamentId, $playoffSlot, $winnerId);
                break;
            case PlayerSlot::B: $this->gameRepository->setPlayer2Slot($tournamentId, $playoffSlot, $winnerId);
                break;
        }
    }
}











