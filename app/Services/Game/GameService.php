<?php

namespace App\Services\Game;

use App\DTO\ActiveGameDTO;
use App\DTO\GameResultDTO;
use App\DTO\UpdateGameDTO;
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
use App\Services\League\LeagueStatsService;
use App\Services\Player\PlayerStatsService;
use App\Services\PlayoffGame\PlayoffService;
use App\Services\QuickGame\QuickGameService;
use App\Services\Tournament\TournamentFinishService;
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
        private LeagueStatsService   $leagueStatsService,
        private GameLockService     $gameLockService,
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
            GameKind::QUICK => null,
        };
    }

    public function saveAchievementsForFinishedGame(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $this->assertGameIsFinished($dto->gameResultDTO);
                $this->achievementsService->createMany($dto->achievementsDTOs);
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
                } else if ($gameToUpdate->round === GameStage::FINAL)
                {
                    $this->handleTournamentResultCreating($dto->gameResultDTO->winnerId,
                                                            $dto->gameResultDTO->player1Id,
                                                            $dto->gameResultDTO->player2Id,
                                                            $gameToUpdate->tournamentId,
                                                            GameStage::FINAL,
                                                            1);
                }

                $this->playoffService->update($dto->gameResultDTO, $gameToUpdate);

                if (in_array($gameToUpdate->round, [GameStage::FINAL, GameStage::THIRD], true)) {
                    $this->tournamentFinishService->tryFinish($gameToUpdate->tournamentId);
                }

                $this->achievementsService->createMany($dto->achievementsDTOs);

                // Zapisz szczegóły legów jeśli są dostępne
                if (!empty($dto->legsDTOs)) {
                    $this->gameLegService->createMany(
                        $dto->legsDTOs,
                        gameId: null,
                        playoffGameId: $gameToUpdate->id
                    );
                }

                $this->recalculatePlayerAndLeagueStats($dto->gameResultDTO);
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

                $this->recalculatePlayerAndLeagueStats($dto->gameResultDTO);

                $this->handlePlayoffStart($dto->gameResultDTO->tournamentId);

            });

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
            $this->recalculatePlayerAndLeagueStats($dto);
            $this->handlePlayoffStart($dto->tournamentId);
        });
    }

    private function finalizePlayoffGameFromScoring(GameResultDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            $gameToUpdate = $this->playoffGameRepository->find($dto->gameId);

            if (! in_array($gameToUpdate->round, [GameStage::FINAL, GameStage::THIRD, GameStage::SEMI])) {
                $this->handleTournamentResultCreating(
                    $dto->winnerId,
                    $dto->player1Id,
                    $dto->player2Id,
                    $gameToUpdate->tournamentId,
                    $gameToUpdate->round,
                    null,
                );
            } elseif ($gameToUpdate->round === GameStage::THIRD) {
                $this->handleTournamentResultCreating(
                    $dto->winnerId,
                    $dto->player1Id,
                    $dto->player2Id,
                    $gameToUpdate->tournamentId,
                    GameStage::THIRD,
                    3,
                );
            } elseif ($gameToUpdate->round === GameStage::FINAL) {
                $this->handleTournamentResultCreating(
                    $dto->winnerId,
                    $dto->player1Id,
                    $dto->player2Id,
                    $gameToUpdate->tournamentId,
                    GameStage::FINAL,
                    1,
                );
            }

            $this->playoffService->applyWinnerAdvancement($dto, $gameToUpdate);
            $this->recalculatePlayerAndLeagueStats($dto);

            if (in_array($gameToUpdate->round, [GameStage::FINAL, GameStage::THIRD], true)) {
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

    private function recalculatePlayerAndLeagueStats(GameResultDTO $dto): void
    {
        foreach ([$dto->player1Id, $dto->player2Id] as $playerId) {
            $player = $this->playerRepository->findById($playerId);
            if ($player !== null && $player->userId !== null) {
                $this->playerStatsService->recalculateAndSave($player->id);
            }
        }

        $tournamentId = $dto->tournamentId;
        if ($tournamentId !== null) {
            $leagueId = $this->tournamentRepository->getLeagueIdForTournament($tournamentId);
            if ($leagueId !== null) {
                $this->leagueStatsService->recalculateForLeague($leagueId);
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
                $this->tournamentRepository->changeStatus($tournamentId,
                    TournamentStatus::PLAYOFF);
            } catch (Throwable $e) {

            }
        }
    }
}












