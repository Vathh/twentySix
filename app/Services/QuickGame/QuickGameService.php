<?php

namespace App\Services\QuickGame;

use App\DTO\QuickGame\QuickGameResultDTO;
use App\DTO\UpdateGameDTO;
use App\Domain\Game\QuickGameDomain;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Models\QuickGame\QuickGame;
use App\Repositories\QuickGame\QuickGameRepository;
use App\Repositories\Player\PlayerRepository;
use App\Services\Achievements\AchievementsService;
use App\Services\Game\GameLegService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuickGameService
{
    public function __construct(
        private QuickGameRepository $quickGameRepository,
        private PlayerRepository $playerRepository,
        private AchievementsService $achievementsService,
        private GameLegService $gameLegService
    )
    {
    }

    /**
     * Tworzy szybki mecz między dwoma zarejestrowanymi graczami
     * @param int $player1Id ID gracza 1 (z Player, nie User)
     * @param int $player2Id ID gracza 2 (z Player, nie User)
     * @param int $requestingUserId ID użytkownika który tworzy mecz
     * @return int ID utworzonego meczu
     * @throws \RuntimeException
     */
    public function createQuickGame(int $player1Id, int $player2Id, int $requestingUserId, int $legsCount = 2): int
    {
        // Sprawdź czy gracze są zarejestrowani (mają user_id)
        $player1 = $this->playerRepository->findById($player1Id);
        $player2 = $this->playerRepository->findById($player2Id);

        if (!$player1 || !$player1->userId) {
            throw new \RuntimeException('Gracz 1 musi być zarejestrowanym użytkownikiem');
        }

        if (!$player2 || !$player2->userId) {
            throw new \RuntimeException('Gracz 2 musi być zarejestrowanym użytkownikiem');
        }

        // Sprawdź czy użytkownik tworzący mecz jest jednym z graczy
        if ($requestingUserId !== $player1->userId && $requestingUserId !== $player2->userId) {
            throw new \RuntimeException('Możesz tworzyć mecze tylko z własnym udziałem');
        }

        return $this->quickGameRepository->create($player1Id, $player2Id, $legsCount);
    }

    /**
     * Zapisuje wynik szybkiego meczu
     * @param UpdateGameDTO $dto
     * @return bool
     */
    public function updateQuickGame(UpdateGameDTO $dto): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                // Sprawdź czy to szybki mecz
                if ($dto->gameResultDTO->type !== GameType::QUICK_MATCH) {
                    throw new \RuntimeException('To nie jest szybki mecz');
                }

                // Pobierz mecz i sprawdź poprawność danych
                $quickGame = $this->quickGameRepository->find($dto->gameResultDTO->gameId);
                $quickGame->checkUpdateDataAccuracy(
                    $dto->gameResultDTO->player1Id,
                    $dto->gameResultDTO->player2Id,
                    $dto->gameResultDTO->winnerId
                );

                // Zakończ mecz
                $this->quickGameRepository->finish($dto->gameResultDTO);

                // Zapisz achievementy
                $this->achievementsService->createMany($dto->achievementsDTOs);

                // Zapisz szczegóły legów jeśli są dostępne
                if (!empty($dto->legsDTOs)) {
                    $this->gameLegService->createMany(
                        $dto->legsDTOs,
                        gameId: null,
                        playoffGameId: null,
                        quickGameId: $dto->gameResultDTO->gameId
                    );
                }
            });

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Pobiera aktywne szybkie mecze użytkownika
     * @param int $userId
     * @return Collection<int, QuickGameDomain>
     */
    public function getActiveForUser(int $userId): Collection
    {
        return $this->quickGameRepository->getActiveForUser($userId);
    }

    /**
     * Ustawia status szybkiego meczu na "w trakcie"
     * @param int $gameId
     * @return void
     */
    public function setStatusInProgress(int $gameId): void
    {
        $this->quickGameRepository->setStatusInProgress($gameId);
    }

    /**
     * Zapis wyniku z lobby (bez wcześniejszego quick_games) lub samych achievementów po scoring API.
     */
    public function finishFromMobile(QuickGameResultDTO $dto, ?int $lobbyId = null, ?int $gameId = null): void
    {
        DB::transaction(function () use ($dto, $lobbyId, $gameId) {
            if ($gameId !== null && $dto->players === []) {
                QuickGame::findOrFail($gameId);
                $this->achievementsService->createMany($dto->achievements);

                return;
            }

            if ($gameId !== null) {
                $game = QuickGame::findOrFail($gameId);
                $this->applyPlayerResultsToGame($game, $dto->players);
                $this->achievementsService->createMany($dto->achievements);

                return;
            }

            if ($dto->players === []) {
                throw new \RuntimeException('Brak wyników graczy');
            }

            $playerIds = array_map(fn ($p) => $p->playerId, $dto->players);
            $newGameId = $this->quickGameRepository->createWithResults($playerIds, $lobbyId);
            $this->quickGameRepository->saveResults($newGameId, $dto->players);

            $sorted = $dto->players;
            usort($sorted, fn ($a, $b) => ($b->place ?? 0) <=> ($a->place ?? 0));
            $winner = $sorted[0] ?? null;
            if ($winner) {
                $game = QuickGame::findOrFail($newGameId);
                $p1Score = 0;
                $p2Score = 0;
                foreach ($dto->players as $pr) {
                    if ((int) $pr->playerId === (int) $game->player1_id) {
                        $p1Score = (int) $pr->score;
                    }
                    if ((int) $pr->playerId === (int) $game->player2_id) {
                        $p2Score = (int) $pr->score;
                    }
                }
                $game->update([
                    'player1_score' => $p1Score,
                    'player2_score' => $p2Score,
                    'winner_id' => $winner->playerId,
                    'status' => GameStatus::FINISHED,
                ]);
            }

            $this->achievementsService->createMany($dto->achievements);
        });
    }

    /**
     * @param  \App\DTO\QuickGame\PlayerResultDTO[]  $players
     */
    private function applyPlayerResultsToGame(QuickGame $game, array $players): void
    {
        $this->quickGameRepository->saveResults($game->id, $players);
        $p1Score = 0;
        $p2Score = 0;
        $winnerId = null;
        foreach ($players as $pr) {
            if ((int) $pr->playerId === (int) $game->player1_id) {
                $p1Score = (int) $pr->score;
            }
            if ((int) $pr->playerId === (int) $game->player2_id) {
                $p2Score = (int) $pr->score;
            }
            if ($pr->place === 1) {
                $winnerId = $pr->playerId;
            }
        }
        $game->update([
            'player1_score' => $p1Score,
            'player2_score' => $p2Score,
            'winner_id' => $winnerId ?? $game->winner_id,
            'status' => GameStatus::FINISHED,
        ]);
    }
}












