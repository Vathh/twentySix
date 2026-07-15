<?php

namespace App\Services\QuickGame;

use App\Models\QuickGame\QuickGame;
use App\Services\Achievements\AchievementsService;
use Illuminate\Support\Facades\DB;

class QuickGameService
{
    public function __construct(
        private AchievementsService $achievementsService,
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
            QuickGame::findOrFail($gameId);
            if ($achievements !== []) {
                $this->achievementsService->createMany($achievements);
            }
        });
    }
}
