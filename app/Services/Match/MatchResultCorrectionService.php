<?php

namespace App\Services\Match;

use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\MatchKind;
use App\Enums\PlayoffSlot;
use App\Models\Game\Game;
use App\Repositories\Game\GameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\Game\GameService;
use App\Services\GroupStanding\GroupStandingService;
use App\Services\League\LeagueStatsService;
use App\Services\PlayoffGame\PlayoffService;
use App\Services\Player\PlayerStatsService;
use App\Services\Tournament\TournamentResultService;
use App\Support\Match\MatchLegScoreValidator;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class MatchResultCorrectionService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private GameService $gameService,
        private GroupStandingService $groupStandingService,
        private PlayoffService $playoffService,
        private PlayerRepository $playerRepository,
        private PlayerStatsService $playerStatsService,
        private LeagueStatsService $leagueStatsService,
        private TournamentRepository $tournamentRepository,
        private TournamentResultService $tournamentResultService,
    ) {
    }

    public function applyFromWeb(
        MatchKind $kind,
        int $matchId,
        int $player1Score,
        int $player2Score,
    ): void {
        match ($kind) {
            MatchKind::GROUP => $this->applyGroupResult($matchId, $player1Score, $player2Score),
            MatchKind::PLAYOFF => $this->applyPlayoffResult($matchId, $player1Score, $player2Score),
            MatchKind::QUICK => throw new DomainException('Korekta wyniku na webie dotyczy tylko meczów turniejowych.'),
        };
    }

    public function applyWalkoverFromWeb(MatchKind $kind, int $matchId, int $winnerPlayerId): void
    {
        [$player1Id] = match ($kind) {
            MatchKind::GROUP => $this->resolveGroupPlayerIds($matchId),
            MatchKind::PLAYOFF => $this->resolvePlayoffPlayerIds($matchId),
            MatchKind::QUICK => throw new DomainException('Walkower na webie dotyczy tylko meczów turniejowych.'),
        };

        [$player1Score, $player2Score] = MatchLegScoreValidator::walkoverScores(
            $winnerPlayerId,
            $player1Id,
        );

        $this->applyFromWeb($kind, $matchId, $player1Score, $player2Score);
    }

    private function applyGroupResult(int $matchId, int $player1Score, int $player2Score): void
    {
        $game = $this->gameRepository->find($matchId);
        $gameModel = Game::findOrFail($matchId);

        if ($game->player1 === null || $game->player2 === null) {
            throw new DomainException('Mecz nie ma przypisanych graczy.');
        }

        $winnerId = MatchLegScoreValidator::validateAndResolveWinner(
            $game->player1->id,
            $game->player2->id,
            $player1Score,
            $player2Score,
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
                $this->recalculatePlayerAndLeagueStats($dto);
            });

            return;
        }

        if (! $this->gameService->update($dto)) {
            throw new DomainException('Nie udało się zapisać wyniku meczu grupowego.');
        }
    }

    private function applyPlayoffResult(int $matchId, int $player1Score, int $player2Score): void
    {
        $game = $this->playoffGameRepository->find($matchId);

        if ($game->player1Id === null || $game->player2Id === null) {
            throw new DomainException('Mecz playoff nie ma przypisanych graczy.');
        }

        $winnerId = MatchLegScoreValidator::validateAndResolveWinner(
            $game->player1Id,
            $game->player2Id,
            $player1Score,
            $player2Score,
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
                    $destination = $game->winnerDestinationSlot->toDestination();
                    $this->resetDownstreamPlayoffAndPodium(
                        $game->tournamentId,
                        $destination->playoffSlot,
                        includeThird: $destination->playoffSlot === PlayoffSlot::FINAL,
                    );
                }

                $this->playoffGameRepository->finish($dto);
                $this->playoffService->applyWinnerAdvancement($dto, $game);

                if ($winnerChanged) {
                    $this->syncPodiumAfterPlayoffCorrection($game->round, $dto);
                }

                $this->recalculatePlayerAndLeagueStats(new UpdateGameDTO($dto, [], []));
            });

            return;
        }

        $updateDto = new UpdateGameDTO($dto, [], []);

        if (! $this->gameService->update($updateDto)) {
            throw new DomainException('Nie udało się zapisać wyniku meczu playoff.');
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveGroupPlayerIds(int $matchId): array
    {
        $game = $this->gameRepository->find($matchId);

        if ($game->player1 === null || $game->player2 === null) {
            throw new DomainException('Mecz nie ma przypisanych graczy.');
        }

        return [$game->player1->id, $game->player2->id];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolvePlayoffPlayerIds(int $matchId): array
    {
        $game = $this->playoffGameRepository->find($matchId);

        if ($game->player1Id === null || $game->player2Id === null) {
            throw new DomainException('Mecz playoff nie ma przypisanych graczy.');
        }

        return [$game->player1Id, $game->player2Id];
    }

    private function resetDownstreamPlayoffAndPodium(
        int $tournamentId,
        PlayoffSlot $slot,
        bool $includeThird = false,
    ): void {
        $this->playoffGameRepository->resetFinishedBranchFromSlot($tournamentId, $slot);

        if ($slot === PlayoffSlot::FINAL) {
            $this->tournamentResultService->clearPodiumStage($tournamentId, GameStage::FINAL);
        }

        if ($slot === PlayoffSlot::THIRD || $includeThird) {
            $this->playoffGameRepository->resetFinishedBranchFromSlot($tournamentId, PlayoffSlot::THIRD);
            $this->tournamentResultService->clearPodiumStage($tournamentId, GameStage::THIRD);
        }
    }

    private function syncPodiumAfterPlayoffCorrection(GameStage $round, GameResultDTO $dto): void
    {
        if ($dto->tournamentId === null) {
            return;
        }

        match ($round) {
            GameStage::FINAL => $this->tournamentResultService->syncFinalPodium(
                $dto->tournamentId,
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
            ),
            GameStage::THIRD => $this->tournamentResultService->syncThirdPodium(
                $dto->tournamentId,
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
            ),
            default => null,
        };
    }

    private function recalculatePlayerAndLeagueStats(UpdateGameDTO $dto): void
    {
        try {
            foreach ([$dto->gameResultDTO->player1Id, $dto->gameResultDTO->player2Id] as $playerId) {
                $player = $this->playerRepository->findById($playerId);
                if ($player !== null && $player->userId !== null) {
                    $this->playerStatsService->recalculateAndSave($player->id);
                }
            }

            $tournamentId = $dto->gameResultDTO->tournamentId;
            if ($tournamentId !== null) {
                $leagueId = $this->tournamentRepository->getLeagueIdForTournament($tournamentId);
                if ($leagueId !== null) {
                    $this->leagueStatsService->recalculateForLeague($leagueId);
                }
            }
        } catch (Throwable) {
            // Statystyki nie powinny blokować korekty wyniku.
        }
    }
}
