<?php

namespace App\DTO;

class UpdateGameDTO
{

    public function __construct(
        public GameResultDTO $gameResultDTO,
        /** @var GameAchievementDTO[] */
        public array $achievementsDTOs
    )
    {
    }

    /**
     * @param array $data
     * @return UpdateGameDTO
     */
    public static function fromArray(array $data): UpdateGameDTO
    {
        return new self(
            gameResultDTO: GameResultDTO::fromArray($data['game']),
            achievementsDTOs: array_map(fn($array) => GameAchievementDTO::fromArray($array), $data['achievements'])
        );
    }
}
