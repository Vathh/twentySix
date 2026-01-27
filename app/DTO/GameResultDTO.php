<?php

namespace App\DTO;

use App\Enums\GameType;

class GameResultDTO
{

    public function __construct(
        public int $gameId,
        public GameType $type,
        public int $player1Id,
        public int $player2Id,
        public int $player1Score,
        public int $player2Score,
        public int $winnerId,
        public ?int $tournamentId = null,
        public int $groupNumber = 0
    )
    {
    }

    public static function fromArray(array $data): GameResultDTO
    {
        $type = GameType::from($data['type']);
        $isQuickGame = $type === GameType::QUICK_MATCH;

        return new self(
            gameId: $data['id'],
            type: $type,
            player1Id: $data['player1Id'],
            player2Id: $data['player2Id'],
            player1Score: $data['player1Score'],
            player2Score: $data['player2Score'],
            winnerId: $data['winnerId'],
            tournamentId: $isQuickGame ? null : ($data['tournamentId'] ?? null),
            groupNumber: $isQuickGame ? 0 : ($data['groupNumber'] ?? 0)
        );
    }
}
