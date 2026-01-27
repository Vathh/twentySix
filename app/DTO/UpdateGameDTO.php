<?php

namespace App\DTO;

class UpdateGameDTO
{

    public function __construct(
        public GameResultDTO $gameResultDTO,
        /** @var GameAchievementDTO[] */
        public array $achievementsDTOs,
        /** @var MatchLegDTO[] */
        public array $legsDTOs = []
    )
    {
    }

    /**
     * @param array $data
     * @return UpdateGameDTO
     */
    public static function fromArray(array $data): UpdateGameDTO
    {
        $legs = isset($data['legs']) && is_array($data['legs'])
            ? array_map(fn($array) => MatchLegDTO::fromArray($array), $data['legs'])
            : [];

        return new self(
            gameResultDTO: GameResultDTO::fromArray($data['game']),
            achievementsDTOs: array_map(fn($array) => GameAchievementDTO::fromArray($array), $data['achievements'] ?? []),
            legsDTOs: $legs
        );
    }
}
