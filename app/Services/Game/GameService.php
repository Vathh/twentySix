<?php

namespace App\Services\Game;

use App\DTO\ActiveGameDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Domain\Tournament\TournamentDomain;
use App\Enums\GameKind;
use App\Enums\GameStage;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\TournamentStatus;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Support\GameScoring\GameScoringContext;
use App\Repositories\Game\GameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\Achievements\AchievementsService;
use App\Services\Game\GameLegService;
use App\Services\Game\GameLockService;
use App\Services\GroupStanding\GroupStandingService;
use App\Services\Player\PlayerStatsService;
use App\Services\PlayoffGame\PlayoffService;
use App\Services\QuickGame\QuickGameService;
use App\Services\Tournament\TournamentFinishService;
use App\Services\Tournament\TournamentGroupMatrixLiveService;
use App\Services\Tournament\TournamentResultService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class GameService
{

    public function __construct(
        private GameRepository       $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private PlayerRepository    $playerRepository,
        private GroupStandingService $groupStandingService,
        private AchievementsService  $achievementsService,
        private PlayoffService       $playoffService,
        private TournamentRepository $tournamentRepository,
        private TournamentResultService  $tournamentResultService,
        private TournamentFinishService $tournamentFinishService,
        private GameLegService      $gameLegService,
        private PlayerStatsService   $playerStatsService,
        private GameLockService     $gameLockService,
        private TournamentGroupMatrixLiveService $groupMatrixLiveService,
    )
    {
    }

    public function setStatusInProgress(int $gameId): void
    {
        $this->gameLockService->lock($gameId, GameType::GROUP);
    }

    public function lockGame(int $gameId, GameType $type): void
    {
        $this->gameLockService->lock($gameId, $type);
    }

    public function releaseGameLock(int $gameId, GameType $type): void
    {
        $this->gameLockService->release($gameId, $type);
    }

    /**
     * Aktualizacja meczu turniejowego.
     *
     * 1. Mecz FINISHED + niepusta tablica achievements → tylko achievementy (mobile po closeLeg).
     * 2. Mecz FINISHED bez achievements → odrzucone (wynik ustawia scoring API).
     * 3. Mecz SCHEDULED → legacy bulk finish (testy; produkcja używa scoring API + finalizeTournamentGameFromScoring).
     *
     * Quick game: wyłącznie POST /api/quick-game/update (achievementy po FFA).
     */
    public function update(UpdateGameDTO $dto): bool
    {
        if ($this->isFinishedGameAchievementsUpdate($dto)) {
            return $this->saveAchievementsForFinishedGame($dto);
        }

        if ($this->isGameAlreadyFinished($dto)) {
            \Log::warning('Rejected game update: game already finished', [
                'gameId' => $dto->gameResultDTO->gameId,
                'type' => $dto->gameResultDTO->type->value,
            ]);

            return false;
        }

        if ($dto->gameResultDTO->type === GameType::PLAYOFF) {
            return $this->handlePlayoffGameUpdate($dto);
        }

        if ($dto->gameResultDTO->type === GameType::GROUP) {
            return $this->handleGroupGameUpdate($dto);
        }

        // Quick games są obsługiwane przez /api/quick-game/update
        return false;
    }

    /**
     * Po zamknięciu ostatniego lega przez scoring API — tabele, playoff, statystyki (bez ponownego finish).
     */
    public function finalizeTournamentGameFromScoring(GameScoringContext $context, Game|PlayoffGame $gameModel): void
    {
        if ($context->tournamentId === null) {
            return;
        }

        if ($gameModel->status !== GameStatus::FINISHED) {
            return;
        }

        $dto = $this->buildGameResultDtoFromModel($context, $gameModel);

        match ($context->kind) {
            GameKind::GROUP => $this->finalizeGroupGameFromScoring($dto),
            GameKind::PLAYOFF => $this->finalizePlayoffGameFromScoring($dto),
            GameKind::QUICK, GameKind::LEAGUE => null,
        };
    }

    public function saveAchievementsForFinishedGame(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $this->assertGameIsFinished($dto->gameResultDTO);
                $this->achievementsService->createMany($dto->achievementsDTOs);
                $this->recalculatePlayerStats($dto->gameResultDTO);
            });

            return true;
        } catch (Throwable $e) {
            \Log::error('Achievements-only game update failed', [
                'gameId' => $dto->gameResultDTO->gameId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param int $tournamentId
     * @return Collection<ActiveGameDTO>
     */
    public function getActiveGames(int $tournamentId): Collection
    {
        try {
            $games = $this->gameRepository->getActive($tournamentId);
            $playoffGames = $this->playoffGameRepository->getActive($tournamentId);

            return collect($games->map(fn($game) => ActiveGameDTO::fromGame($game)))
                    ->merge(
                        $playoffGames
                            ->map(fn($game) => ActiveGameDTO::fromPlayoffGameDomain($game))
                            ->filter(),
                    );
        } catch (Throwable $e) {
            return collect();
        }
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

                $this->recordPlayoffEliminationResults($dto->gameResultDTO, $gameToUpdate);

                $this->playoffService->update($dto->gameResultDTO, $gameToUpdate);

                if ($this->shouldTryFinishAfterPlayoff($gameToUpdate)) {
                    $this->tournamentFinishService->tryFinish($gameToUpdate->tournamentId);
                }

                $this->achievementsService->createMany($dto->achievementsDTOs);

                if (!empty($dto->legsDTOs)) {
                    $this->gameLegService->createMany(
                        $dto->legsDTOs,
                        gameId: null,
                        playoffGameId: $gameToUpdate->id
                    );
                }

                $this->recalculatePlayerStats($dto->gameResultDTO);
            });

            return true;
        } catch (Throwable $e) {
            \Log::error('Playoff game update failed', [
                'gameId' => $dto->gameResultDTO->gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function recordPlayoffEliminationResults(
        \App\DTO\GameResultDTO $dto,
        \App\Domain\Game\PlayoffGameDomain $game,
    ): void {
        $round = $game->round;
        $isDe = in_array($game->bracketSide, [
            \App\Enums\BracketSide::Winners,
            \App\Enums\BracketSide::Losers,
            \App\Enums\BracketSide::GrandFinal,
        ], true);

        if ($isDe) {
            $this->recordDoubleElimResults($dto, $game);

            return;
        }

        if (! in_array($round, [
            GameStage::FINAL->value,
            GameStage::THIRD->value,
            GameStage::SEMI->value,
        ], true)) {
            $stage = $game->roundStage() ?? GameStage::QUARTER;
            $this->handleTournamentResultCreating(
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
                $game->tournamentId,
                $stage,
                null,
            );
        } elseif ($round === GameStage::THIRD->value) {
            $this->handleTournamentResultCreating(
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
                $game->tournamentId,
                GameStage::THIRD,
                3,
            );
        } elseif ($round === GameStage::FINAL->value) {
            $this->handleTournamentResultCreating(
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
                $game->tournamentId,
                GameStage::FINAL,
                1,
            );
        }
    }

    private function recordDoubleElimResults(
        \App\DTO\GameResultDTO $dto,
        \App\Domain\Game\PlayoffGameDomain $game,
    ): void {
        $bracketSize = $this->tournamentRepository->getBracketSize($game->tournamentId);
        $loserId = $dto->winnerId === $dto->player1Id ? $dto->player2Id : $dto->player1Id;

        if ($game->slot === 'GF1') {
            $tournament = \App\Models\Tournament\Tournament::query()->find($game->tournamentId);
            $reset = $tournament?->grand_final_mode === \App\Enums\GrandFinalMode::Reset;
            $lbWon = $dto->winnerId === $dto->player2Id;

            if ($reset && $lbWon) {
                return; // będzie GF2
            }

            $this->handleTournamentResultCreating(
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
                $game->tournamentId,
                GameStage::FINAL,
                1,
            );

            return;
        }

        if ($game->slot === 'GF2') {
            $this->handleTournamentResultCreating(
                $dto->winnerId,
                $dto->player1Id,
                $dto->player2Id,
                $game->tournamentId,
                GameStage::FINAL,
                1,
            );

            return;
        }

        $place = \App\Support\Tournament\DoubleEliminationPlacement::placeForLoser(
            $game->bracketSide,
            $game->round,
            $game->slot,
            $bracketSize,
            $game->loserDestinationSlot !== null,
        );

        if ($place === null || $loserId <= 0) {
            return;
        }

        $this->tournamentResultService->createForPlayoff(
            $game->tournamentId,
            $loserId,
            \App\Support\Tournament\DoubleEliminationPlacement::resultStageForRound($game->round),
            $place,
        );
    }

    private function shouldTryFinishAfterPlayoff(\App\Domain\Game\PlayoffGameDomain $game): bool
    {
        if ($game->slot === 'GF2') {
            return true;
        }

        if ($game->slot === 'GF1') {
            $tournament = \App\Models\Tournament\Tournament::query()->find($game->tournamentId);
            if ($tournament?->grand_final_mode === \App\Enums\GrandFinalMode::Reset) {
                // Finish tylko gdy nie ma otwartego GF2 z graczami — uproszczenie: finish gdy GF2 nie ma obu graczy
                $gf2 = \App\Models\PlayoffGame\PlayoffGame::query()
                    ->where('tournament_id', $game->tournamentId)
                    ->where('slot', 'GF2')
                    ->first();

                return $gf2 === null
                    || $gf2->player1_id === null
                    || $gf2->player2_id === null
                    || $gf2->status === \App\Enums\GameStatus::FINISHED;
            }

            return true;
        }

        return in_array($game->round, [GameStage::FINAL->value, GameStage::THIRD->value], true);
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
                    $this->gameLegService->createMany(
                        $dto->legsDTOs,
                        gameId: $dto->gameResultDTO->gameId,
                        playoffGameId: null
                    );
                }

                $this->recalculatePlayerStats($dto->gameResultDTO);

                $this->handlePlayoffStart($dto->gameResultDTO->tournamentId);

            });

            $finishedGame = $this->gameRepository->findModelOrNull($dto->gameResultDTO->gameId);
            if ($finishedGame !== null) {
                $this->groupMatrixLiveService->pushFromGroupGame($finishedGame, true);
            }

            return true;
        } catch (Throwable $e) {
            \Log::error('Group game update failed', [
                'gameId' => $dto->gameResultDTO->gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function finalizeGroupGameFromScoring(GameResultDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $this->groupStandingService->updateStandingsDetails($dto);
            $this->groupStandingService->updateGroupStandings($dto->tournamentId, $dto->groupNumber);
            $this->recalculatePlayerStats($dto);
            $this->handlePlayoffStart($dto->tournamentId);
        });

        // Broadcast z standings jest w GameScoringService::closeLeg (afterCommit).
    }

    private function finalizePlayoffGameFromScoring(GameResultDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $gameToUpdate = $this->playoffGameRepository->find($dto->gameId);

            $this->recordPlayoffEliminationResults($dto, $gameToUpdate);
            $this->playoffService->applyWinnerAdvancement($dto, $gameToUpdate);
            $this->recalculatePlayerStats($dto);

            if ($this->shouldTryFinishAfterPlayoff($gameToUpdate)) {
                $this->tournamentFinishService->tryFinish($gameToUpdate->tournamentId);
            }
        });
    }

    private function buildGameResultDtoFromModel(GameScoringContext $context, Game|PlayoffGame $gameModel): GameResultDTO
    {
        return new GameResultDTO(
            gameId: (int) $gameModel->id,
            type: $context->kind === GameKind::PLAYOFF ? GameType::PLAYOFF : GameType::GROUP,
            player1Id: (int) $gameModel->player1_id,
            player2Id: (int) $gameModel->player2_id,
            player1Score: (int) $gameModel->player1_score,
            player2Score: (int) $gameModel->player2_score,
            winnerId: (int) $gameModel->winner_id,
            tournamentId: $context->tournamentId,
            groupNumber: $gameModel instanceof Game ? (int) $gameModel->group_number : 0,
        );
    }

    private function isFinishedGameAchievementsUpdate(UpdateGameDTO $dto): bool
    {
        if ($dto->achievementsDTOs === []) {
            return false;
        }

        return match ($dto->gameResultDTO->type) {
            GameType::GROUP => $this->gameRepository->find($dto->gameResultDTO->gameId)?->status === GameStatus::FINISHED,
            GameType::PLAYOFF => $this->playoffGameRepository->find($dto->gameResultDTO->gameId)?->status === GameStatus::FINISHED,
            default => false,
        };
    }

    private function isGameAlreadyFinished(UpdateGameDTO $dto): bool
    {
        return match ($dto->gameResultDTO->type) {
            GameType::GROUP => $this->gameRepository->find($dto->gameResultDTO->gameId)?->status === GameStatus::FINISHED,
            GameType::PLAYOFF => $this->playoffGameRepository->find($dto->gameResultDTO->gameId)?->status === GameStatus::FINISHED,
            default => false,
        };
    }

    private function assertGameIsFinished(GameResultDTO $dto): void
    {
        $finished = match ($dto->type) {
            GameType::GROUP => $this->gameRepository->find($dto->gameId)?->status === GameStatus::FINISHED,
            GameType::PLAYOFF => $this->playoffGameRepository->find($dto->gameId)?->status === GameStatus::FINISHED,
            default => false,
        };

        if (! $finished) {
            throw new \DomainException('Mecz nie jest zakończony — nie można zapisać samych achievementów.');
        }
    }

    private function recalculatePlayerStats(GameResultDTO $dto): void
    {
        foreach ([$dto->player1Id, $dto->player2Id] as $playerId) {
            $player = $this->playerRepository->findById($playerId);
            if ($player !== null && $player->userId !== null) {
                $this->playerStatsService->recalculateAndSave($player->id);
            }
        }
    }

    private function handlePlayoffStart(int $tournamentId): void
    {
        if($this->gameRepository->checkIfPlayoffShouldBeStarted($tournamentId))
        {
            $this->tournamentResultService->createForGroupLosers($tournamentId);
            $this->playoffService->generateBracket($tournamentId);
            try {
                $tournament = $this->tournamentRepository->findModel($tournamentId);
                if (TournamentDomain::fromEloquent($tournament)->canTransitionTo(TournamentStatus::PLAYOFF)) {
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::PLAYOFF);
                }
            } catch (Throwable $e) {

            }
        }
    }
}












