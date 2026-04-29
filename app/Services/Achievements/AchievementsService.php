<?php

namespace App\Services\Achievements;

use App\DTO\GameAchievementDTO;
use App\Repositories\Achievements\AchievementsRepository;

class AchievementsService
{

    public function __construct(
        private AchievementsRepository $achievementsRepository
    )
    {
    }

    /**
     * @param GameAchievementDTO[] $achievements array
     * @return void
     */
    public function createMany(array $achievements): void
    {
        $this->achievementsRepository->createMany($achievements);
    }
}











