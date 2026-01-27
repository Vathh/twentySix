<?php

namespace App\Services;

use App\DTO\UpdateGameDTO;
use App\Domain\Game\QuickGameDomain;
use App\Enums\GameType;
use App\Repositories\QuickGameRepository;
use App\Repositories\PlayerRepository;
use App\Services\AchievementsService;
use App\Services\MatchLegService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class QuickGameService
{
    public function __construct(
        private QuickGameRepository $quickGameRepository,
        private PlayerRepository $playerRepository,
        private AchievementsService $achievementsService,
        private MatchLegService $matchLegService
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
    public function createQuickGame(int $player1Id, int $player2Id, int $requestingUserId): int
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

        return $this->quickGameRepository->create($player1Id, $player2Id);
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
                    $this->matchLegService->createMany(
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
}
