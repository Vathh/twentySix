<?php

namespace App\Repositories\Achievements;

use App\DTO\GameAchievementDTO;
use App\Models\Achievement;

class AchievementsRepository
{
    public function createMany(array $achievements): void
    {
        $mapped = array_map(fn (GameAchievementDTO $dto) => [
                                'player_id' => $dto->playerId,
                                "tournament_id" => $dto->tournamentId, // null dla szybkich meczów
                                "value" => $dto->value,
                                "type" => $dto->type,
                                'created_at' => now(),
                                'updated_at' => now()
                            ], $achievements);

        Achievement::insert($mapped);
    }
}











