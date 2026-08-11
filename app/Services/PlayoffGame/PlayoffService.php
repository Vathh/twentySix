<?php

namespace App\Services\PlayoffGame;

use App\Domain\Game\PlayoffGameDomain;
use App\Domain\Game\WinnerDestination;
use App\DTO\GameResultDTO;
use App\Enums\GameType;
use App\Enums\GrandFinalMode;
use App\Enums\PlayerSlot;
use App\Factories\DoubleEliminationBracketFactory;
use App\Factories\PlayoffBracketFactory;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentMatchFormatRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Support\Tournament\PlayoffByePairing;
use App\Support\Tournament\PlayoffFirstRoundPairing;
use App\Support\Tournament\PlayoffSlotIds;
use App\Models\Tournament\Tournament;

class PlayoffService
{
    private bool $resolvingByes = false;

    public function __construct(
        private PlayoffBracketFactory $bracketFactory,
        private DoubleEliminationBracketFactory $doubleElimFactory,
        private PlayoffGameRepository $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
        private TournamentRepository $tournamentRepository,
        private TournamentMatchFormatRepository $matchFormatRepository,
    ) {
    }

    public function generateBracket(int $tournamentId): void
    {
        $advancesByGroup = $this->tournamentRepository->getGroupAdvancesByGroupNumber($tournamentId);

        $advancingPlayers = $this->groupStandingRepository
            ->getAdvancingPlayersWithGroups($tournamentId, $advancesByGroup)
            ->all();

        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);

        $firstRoundPairs = PlayoffFirstRoundPairing::pair($advancingPlayers);

        $playoffGames = $this->bracketFactory->create($tournamentId, $bracketSize, $firstRoundPairs);

        $formatsByStage = $this->matchFormatRepository->getForTournament($tournamentId)
            ->mapWithKeys(fn ($row) => [$row->stage => $row->toMatchFormat()])
            ->all();

        $this->gameRepository->createMany($playoffGames, $formatsByStage);
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function generateSingleEliminationBracket(int $tournamentId, array $playerIds): void
    {
        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
        $firstRoundPairs = PlayoffByePairing::pair($playerIds, $bracketSize);
        $playoffGames = $this->bracketFactory->create($tournamentId, $bracketSize, $firstRoundPairs);

        $formatsByStage = $this->matchFormatRepository->getForTournament($tournamentId)
            ->mapWithKeys(fn ($row) => [$row->stage => $row->toMatchFormat()])
            ->all();

        $this->gameRepository->createMany($playoffGames, $formatsByStage);
        $this->resolveScheduledByes($tournamentId);
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function generateDoubleEliminationBracket(int $tournamentId, array $playerIds): void
    {
        $bracketSize = $this->tournamentRepository->getBracketSize($tournamentId);
        $tournament = Tournament::query()->findOrFail($tournamentId);
        $reset = ($tournament->grand_final_mode?->value ?? GrandFinalMode::Reset->value)
            === GrandFinalMode::Reset->value;

        $firstRoundPairs = PlayoffByePairing::pair($playerIds, $bracketSize);
        $playoffGames = $this->doubleElimFactory->create(
            $tournamentId,
            $bracketSize,
            $firstRoundPairs,
            $reset,
        );

        $formatsByStage = $this->matchFormatRepository->getForTournament($tournamentId)
            ->mapWithKeys(fn ($row) => [$row->stage => $row->toMatchFormat()])
            ->all();

        $this->gameRepository->createMany($playoffGames, $formatsByStage);
        $this->resolveScheduledByes($tournamentId);
    }

    /**
     * Zamyka mecze bye (gracz vs wolny los oraz bye vs bye) i kaskaduje awanse.
     * Wołane po generacji drabinki oraz po każdym wpisaniu gracza w slot.
     */
    public function resolveScheduledByes(int $tournamentId): void
    {
        if ($this->resolvingByes) {
            return;
        }

        $this->resolvingByes = true;

        try {
            for ($guard = 0; $guard < 128; $guard++) {
                $progress = false;

                foreach ($this->gameRepository->getScheduledDoubleByes($tournamentId) as $game) {
                    if ($game->id === null) {
                        continue;
                    }
                    $this->gameRepository->finishEmptyByeMatch($game->id);
                    $progress = true;
                }

                foreach ($this->gameRepository->getScheduledByes($tournamentId) as $game) {
                    $winnerId = $game->byeWinnerId();
                    if ($winnerId === null || $game->id === null) {
                        continue;
                    }

                    $dto = new GameResultDTO(
                        gameId: $game->id,
                        type: GameType::PLAYOFF,
                        player1Id: $game->player1Id ?? 0,
                        player2Id: $game->player2Id ?? 0,
                        player1Score: $game->player1Id !== null ? 1 : 0,
                        player2Score: $game->player2Id !== null ? 1 : 0,
                        winnerId: $winnerId,
                        tournamentId: $tournamentId,
                    );

                    $this->gameRepository->finish($dto);
                    $this->propagateResult($dto, $game);
                    $progress = true;
                }

                if (! $progress) {
                    break;
                }
            }
        } finally {
            $this->resolvingByes = false;
        }
    }

    public function update(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        $this->gameRepository->finish($dto);
        $this->applyWinnerAdvancement($dto, $gameToUpdate);
    }

    public function applyWinnerAdvancement(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        $this->propagateResult($dto, $gameToUpdate);
        $this->resolveScheduledByes($gameToUpdate->tournamentId);
    }

    private function propagateResult(GameResultDTO $dto, PlayoffGameDomain $gameToUpdate): void
    {
        if ($gameToUpdate->slot === 'GF1') {
            $this->advanceGrandFinal($dto, $gameToUpdate);

            return;
        }

        if ($gameToUpdate->slot === 'GF2' || PlayoffSlotIds::isTerminal($gameToUpdate->slot)) {
            return;
        }

        if ($gameToUpdate->winnerDestinationSlot !== null) {
            $winnerDestination = WinnerDestination::parse($gameToUpdate->winnerDestinationSlot);
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                $winnerDestination->playoffSlot,
                $dto->winnerId,
                $winnerDestination->playerSlot,
            );
        }

        $loserId = $dto->winnerId === $dto->player1Id ? $dto->player2Id : $dto->player1Id;

        if ($loserId > 0 && $gameToUpdate->loserDestinationSlot !== null) {
            $loserDestination = WinnerDestination::parse($gameToUpdate->loserDestinationSlot);
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                $loserDestination->playoffSlot,
                $loserId,
                $loserDestination->playerSlot,
            );
        } elseif (
            $loserId > 0
            && $gameToUpdate->winnerDestinationSlot !== null
            && WinnerDestination::parse($gameToUpdate->winnerDestinationSlot)->playoffSlot === PlayoffSlotIds::FINAL
        ) {
            // SE: przegrany półfinału → mecz o 3.
            $winnerDestination = WinnerDestination::parse($gameToUpdate->winnerDestinationSlot);
            $this->advancePlayer(
                $gameToUpdate->tournamentId,
                PlayoffSlotIds::THIRD,
                $loserId,
                $winnerDestination->playerSlot,
            );
        }
    }

    private function advanceGrandFinal(GameResultDTO $dto, PlayoffGameDomain $game): void
    {
        $tournament = Tournament::query()->find($game->tournamentId);
        if ($tournament === null) {
            return;
        }

        $mode = $tournament->grand_final_mode ?? GrandFinalMode::Single;
        if ($mode !== GrandFinalMode::Reset) {
            return;
        }

        // Slot A = mistrz WB, B = mistrz LB. Reset tylko gdy wygra LB (player2).
        if ($dto->winnerId !== $dto->player2Id) {
            return;
        }

        $this->advancePlayer($game->tournamentId, 'GF2', $dto->player1Id, PlayerSlot::A);
        $this->advancePlayer($game->tournamentId, 'GF2', $dto->player2Id, PlayerSlot::B);
    }

    public function advancePlayer(int $tournamentId, string $playoffSlot, int $winnerId, PlayerSlot $playerSlot): void
    {
        match ($playerSlot) {
            PlayerSlot::A => $this->gameRepository->setPlayer1Slot($tournamentId, $playoffSlot, $winnerId),
            PlayerSlot::B => $this->gameRepository->setPlayer2Slot($tournamentId, $playoffSlot, $winnerId),
        };
    }
}
