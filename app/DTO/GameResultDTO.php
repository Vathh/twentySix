<?php

namespace App\DTO;

class GameResultDTO
{

    public function __construct(
        public int $gameId,
        public int $player1Id,
        public int $player2Id,
        public int $player1Score,
        public int $player2Score,
        public int $winnerId,
        public int $tournamentId,
        public int $groupNumber
    )
    {
    }

    public static function fromArray(array $data): GameResultDTO
    {
        return new self(
            gameId: $data['game_id'],
            player1Id: $data['player1_id'],
            player2Id: $data['player2_id'],
            player1Score: $data['player1_score'],
            player2Score: $data['player2_score'],
            winnerId: $data['winner_id'],
            tournamentId: $data['tournament_id'],
            groupNumber: $data['group_number']
        );
    }
}
