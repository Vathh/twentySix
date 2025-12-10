<?php

namespace App\DTO;

use App\Enums\AchievementType;

class GameAchievementDTO
{

    public function __construct(
        public int $playerId,
        public int $tournamentId,
        public int $value,
        public AchievementType $achievementType,
    )
    {
    }

    public static function fromArray(array $data): GameAchievementDTO
    {
        return new self(
            playerId: $data['player_id'],
            tournamentId: $data['tournament_id'],
            value: $data['value'],
            achievementType: AchievementType::from($data['type'])
        );
    }
}
