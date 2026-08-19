<?php

namespace App\Services\GameScoring;

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\GameKind;
use App\Repositories\Game\GameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Services\Game\GameService;
use App\Services\GroupStanding\GroupStandingService;
use App\Services\PlayoffGame\PlayoffService;
use App\Services\Player\PlayerStatsService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentResultService;
use App\Domain\GameScoring\GameLegScoreValidator;
use App\Domain\GameScoring\MatchFormat;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class GameResultCorrectionService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private GameService $gameService,
        private GroupStandingService $groupStandingService,
        private PlayoffService $playoffService,
        private PlayerRepository $playerRepository,
        private PlayerStatsService $playerStatsService,
        private TournamentResultService $tournamentResultService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
    ) {
    }

    public function applyFromWeb(
        GameKind $kind,
        int $gameId,
        int $player1Score,
        int $player2Score,
    ): void {
        match ($kind) {
            GameKind::GROUP => $this->applyGroupResult($gameId, $player1Score, $player2Score),
            GameKind::PLAYOFF => $this->applyPlayoffResult($gameId, $player1Score, $player2Score),
            GameKind::QUICK, GameKind::LEAGUE => throw new DomainException('Korekta wyniku na webie dotyczy tylko meczów turniejowych.'),
        };
    }

    public function applyWalkoverFromWeb(GameKind $kind, int $gameId, int $winnerPlayerId): void
    {
        [$player1Id, $format] = match ($kind) {
            GameKind::GROUP => $this->resolveGroupContext($gameId),
            GameKind::PLAYOFF => $this->resolvePlayoffContext($gameId),
            GameKind::QUICK, GameKind::LEAGUE => throw new DomainException('Walkower na webie dotyczy tylko meczów turniejowych.'),
        };

        [$player1Score, $player2Score] = GameLegScoreValidator::walkoverScores(
            $winnerPlayerId,
            $player1Id,
            $format,
        );

        $this->applyFromWeb($kind, $gameId, $player1Score, $player2Score);
    }

    private function applyGroupResult(int $gameId, int $player1Score, int $player2Score): void
    {
        $game = $this->gameRepository->find($gameId);
        $gameModel = $this->gameRepository->findModel($gameId);

        if ($game->player1 === null || $game->player2 === null) {
            throw new DomainException('Mecz nie ma przypisanych graczy.');
        }

        $format = MatchFormat::fromRecord($gameModel);

        $winnerId = GameLegScoreValidator::validateAndResolveWinner(
            $game->player1->id,
            $game->player2->id,
            $player1Score,
            $player2Score,
            $format,
        );

        $dto = new UpdateGameDTO(
            gameResultDTO: new GameResultDTO(
                gameId: $game->id,
                type: GameType::GROUP,
                player1Id: $game->player1->id,
                player2Id: $game->player2->id,
                player1Score: $player1Score,
                player2Score: $player2Score,
                winnerId: $winnerId,
                tournamentId: (int) $gameModel->tournament_id,
                groupNumber: (int) $gameModel->group_number,
            ),
            achievementsDTOs: [],
            legsDTOs: [],
        );

        if ($game->status === GameStatus::FINISHED) {
            DB::transaction(function () use ($dto, $game) {
                $this->gameRepository->finish($dto->gameResultDTO);
                $this->groupStandingService->recalculateGroupFromFinishedGames(
                    $dto->gameResultDTO->tournamentId,
                    $dto->gameResultDTO->groupNumber,
                );
                $this->recalculatePlayerStats($dto);
            });

            $fresh = $this->gameRepository->findModelOrNull($gameId);
            if ($fresh !== null) {
                $this->groupMatrixLiveService->pushFromGroupGame($fresh, true);
            }

            return;
        }

        if (! $this->gameService->update($dto)) {
            throw new DomainException('Nie udało się zapisać wyniku meczu grupowego.');
        }
    }

    private function applyPlayoffResult(int $gameId, int $player1Score, int $player2Score): void
    {
        $game = $this->playoffGameRepository->find($gameId);

        if ($game->player1Id === null || $game->player2Id === null) {
            throw new DomainException('Mecz playoff nie ma przypisanych graczy.');
        }

        $gameModel = $this->playoffGameRepository->findModel($gameId);
        $format = MatchFormat::fromRecord($gameModel);

        $winnerId = GameLegScoreValidator::validateAndResolveWinner(
            $game->player1Id,
            $game->player2Id,
            $player1Score,
            $player2Score,
            $format,
        );

        $dto = new GameResultDTO(
            gameId: $game->id,
            type: GameType::PLAYOFF,
            player1Id: $game->player1Id,
            player2Id: $game->player2Id,
            player1Score: $player1Score,
            player2Score: $player2Score,
            winnerId: $winnerId,
            tournamentId: $game->tournamentId,
            groupNumber: 0,
        );

        if ($game->status === GameStatus::FINISHED) {
            DB::transaction(function () use ($dto, $game) {
                $oldWinnerId = $game->winnerId;
                $winnerChanged = $oldWinnerId !== null && $oldWinnerId !== $dto->winnerId;

                if ($winnerChanged && $game->winnerDestinationSlot !== null) {
                    $destination = \App\Domain\Game\WinnerDestination::parse($game->winnerDestinationSlot);
                    $this->resetDownstreamPlayoffAndPodium(
                        $game->tournamentId,
                        $destination->playoffSlot,
                        includeThird: $destination->playoffSlot === \App\Support\Tournament\PlayoffSlotIds::FINAL,
                    );
                }

                $this->playoffGameRepository->finish($dto);
                $this->playoffService->applyWinnerAdvancement($dto, $game);

                if ($winnerChanged) {
                    $this->syncPodiumAfterPlayoffCorrection($game->round, $dto);
                }

                $this->recalculatePlayerStats(new UpdateGameDTO($dto, [], []));
            });

            return;
        }

        $updateDto = new UpdateGameDTO($dto, [], []);

        if (! $this->gameService->update($updateDto)) {
            throw new DomainException('Nie udało się zapisać wyniku meczu playoff.');
        }
    }

    /**
     * @return array{0: int, 1: MatchFormat}
     */
    private function resolveGroupContext(int $gameId): array
    {
        $game = $this->gameRepository->find($gameId);
        $gameModel = $this->gameRepository->findModel($gameId);

        if ($game->player1 === null || $game->player2 === null) {
            throw new DomainException('Mecz nie ma przypisanych graczy.');
        }

        return [$game->player1->id, MatchFormat::fromRecord($gameModel)];
    }

    /**
     * @return array{0: int, 1: MatchFormat}
     */
    private function resolvePlayoffContext(int $gameId): array
    {
        $game = $this->playoffGameRepository->find($gameId);
        $gameModel = $this->playoffGameRepository->findModel($gameId);

        if ($game->player1Id === null || $game->player2Id === null) {
            throw new DomainException('Mecz playoff nie ma przypisanych graczy.');
        }

        return [$game->player1Id, MatchFormat::fromRecord($gameModel)];
    }

    private function resetDownstreamPlayoffAndPodium(
        int $tournamentId,
        string $slot,
        bool $includeThird = false,
    ): void {
        $this->playoffGameRepository->resetFinishedBranchFromSlot($tournamentId, $slot);

        if ($slot === \App\Support\Tournament\PlayoffSlotIds::FINAL) {
            $this->tournamentResultService->clearPodiumStage($tournamentId, GameStage::FINAL);
        }

        if ($slot === \App\Support\Tournament\PlayoffSlotIds::THIRD || $includeThird) {
            $this->playoffGameRepository->resetFinishedBranchFromSlot(
                $tournamentId,
                \App\Support\Tournament\PlayoffSlotIds::THIRD,
            );
            $this->tournamentResultService->clearPodiumStage($tournamentId, GameStage::THIRD);
        }
    }

    private function syncPodiumAfterPlayoffCorrection(string $round, GameResultDTO $dto): void
    {
        if ($dto->tournamentId === null) {
            return;
        }

        match ($round) {
            GameStage::FINAL->value, 'GF', 'GF2' => $this->tournamentResultService->syncFinalPodium(
                $dto->tournamentId,
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
            ),
            GameStage::THIRD->value => $this->tournamentResultService->syncThirdPodium(
                $dto->tournamentId,
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
            ),
            default => null,
        };
    }

    private function recalculatePlayerStats(UpdateGameDTO $dto): void
    {
        try {
            foreach ([$dto->gameResultDTO->player1Id, $dto->gameResultDTO->player2Id] as $playerId) {
                $player = $this->playerRepository->findById($playerId);
                if ($player !== null && $player->userId !== null) {
                    $this->playerStatsService->recalculateAndSave($player->id);
                }
            }
        } catch (Throwable) {
            // Statystyki nie powinny blokować korekty wyniku.
        }
    }
}
