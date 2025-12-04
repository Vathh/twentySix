<?php

namespace App\Repositories;

use App\Models\Achievement;

class AchievementsRepository
{
    public function createMany(array $achievements): void
    {
        Achievement::insert($achievements);
    }
}
