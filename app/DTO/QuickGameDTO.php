<?php

namespace App\DTO;

class QuickGameDTO
{
    public function __construct(
        public int $player1Id,
        public int $player2Id,
    )
    {
    }

    public static function fromArray(array $data): QuickGameDTO
    {
        return new self(
            player1Id: $data['player1Id'],
            player2Id: $data['player2Id'],
        );
    }
}
