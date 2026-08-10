<?php

namespace App\Repositories\Achievements;

use App\DTO\GameAchievementDTO;
use App\Models\Achievements\Achievement;

class AchievementsRepository
{
    public function createMany(array $achievements): void
    {
        if ($achievements === []) {
            return;
        }

        foreach ($achievements as $dto) {
            $query = Achievement::query()
                ->where('player_id', $dto->playerId)
                ->where('type', $dto->type)
                ->where('value', $dto->value);

            if ($dto->tournamentId === null) {
                $query->whereNull('tournament_id');
            } else {
                $query->where('tournament_id', $dto->tournamentId);
            }

            if ($query->exists()) {
                continue;
            }

            Achievement::query()->create([
                'player_id' => $dto->playerId,
                'tournament_id' => $dto->tournamentId,
                'value' => $dto->value,
                'type' => $dto->type,
            ]);
        }
    }
}












