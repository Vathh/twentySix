<?php

namespace App\DTO\QuickGame;

use App\DTO\GameAchievementDTO;

class QuickGameResultDTO
{
    /**
     * @param GameAchievementDTO[] $achievements
     */
    public function __construct(
        public array $achievements,
    ) {
    }

    public static function fromArray(array $data): self
    {
        // Quick games nie mają tournamentId
        $achievements = array_map(function ($array) {
            $array['tournamentId'] = null;

            return GameAchievementDTO::fromArray($array);
        }, $data['achievements'] ?? []);

        return new self(achievements: $achievements);
    }
}
