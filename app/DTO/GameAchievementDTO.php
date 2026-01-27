<?php

namespace App\DTO;

use App\Enums\AchievementType;

class GameAchievementDTO
{

    public function __construct(
        public int             $playerId,
        public ?int            $tournamentId,
        public ?int            $value,
        public AchievementType $type,
    )
    {
    }

    public static function fromArray(array $data): GameAchievementDTO
    {
        return new self(
            playerId: $data['playerId'],
            tournamentId: $data['tournamentId'] ?? null,
            value: $data['value'] ?? null,
            type: AchievementType::from($data['type'])
        );
    }
}
