<?php

namespace App\Services\QuickGame;

use App\Repositories\QuickGame\QuickGameRepository;
use App\Services\Achievements\AchievementsService;
use Illuminate\Support\Facades\DB;

class QuickGameService
{
    public function __construct(
        private AchievementsService $achievementsService,
        private QuickGameRepository $quickGameRepository,
    ) {
    }

    /**
     * Zapis achievementów po zakończeniu quick game FFA (wynik meczu już w bazie).
     *
     * @param  \App\DTO\GameAchievementDTO[]  $achievements
     */
    public function attachAchievements(int $gameId, array $achievements): void
    {
        DB::transaction(function () use ($gameId, $achievements) {
            $this->quickGameRepository->findModel($gameId);
            if ($achievements !== []) {
                $this->achievementsService->createMany($achievements);
            }
        });
    }
}
