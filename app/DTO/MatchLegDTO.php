<?php

namespace App\DTO;

class MatchLegDTO
{
    public function __construct(
        public int $legNumber,
        public int $player1Score,
        public int $player2Score,
        public int $winnerId,
        public ?int $player1Average = null,
        public ?int $player2Average = null,
        public ?int $player1DartsThrown = null,
        public ?int $player2DartsThrown = null,
        public ?int $checkoutScore = null,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
    )
    {
    }

    public static function fromArray(array $data): MatchLegDTO
    {
        return new self(
            legNumber: $data['legNumber'],
            player1Score: $data['player1Score'],
            player2Score: $data['player2Score'],
            winnerId: $data['winnerId'],
            player1Average: $data['player1Average'] ?? null,
            player2Average: $data['player2Average'] ?? null,
            player1DartsThrown: $data['player1DartsThrown'] ?? null,
            player2DartsThrown: $data['player2DartsThrown'] ?? null,
            checkoutScore: $data['checkoutScore'] ?? null,
            startedAt: $data['startedAt'] ?? null,
            finishedAt: $data['finishedAt'] ?? null,
        );
    }
}
