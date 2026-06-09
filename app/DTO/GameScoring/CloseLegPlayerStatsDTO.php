<?php

namespace App\DTO\GameScoring;

class CloseLegPlayerStatsDTO
{
    public function __construct(
        public int $playerId,
        public bool $doubleTracked,
        public ?int $doubleAttempts,
        public ?int $doubleSuccesses,
        public ?float $legAverage = null,
        public ?float $firstNineAverage = null,
        public ?int $highestVisit = null,
        public ?int $highestFinish = null,
        public ?int $dartsThrown = null,
        public ?int $checkoutDart = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            playerId: (int) $data['playerId'],
            doubleTracked: (bool) ($data['doubleTracked'] ?? false),
            doubleAttempts: isset($data['doubleAttempts']) ? (int) $data['doubleAttempts'] : null,
            doubleSuccesses: isset($data['doubleSuccesses']) ? (int) $data['doubleSuccesses'] : null,
            legAverage: isset($data['legAverage']) ? (float) $data['legAverage'] : null,
            firstNineAverage: isset($data['firstNineAverage']) ? (float) $data['firstNineAverage'] : null,
            highestVisit: isset($data['highestVisit']) ? (int) $data['highestVisit'] : null,
            highestFinish: isset($data['highestFinish']) ? (int) $data['highestFinish'] : null,
            dartsThrown: isset($data['dartsThrown']) ? (int) $data['dartsThrown'] : null,
            checkoutDart: isset($data['checkoutDart']) ? (int) $data['checkoutDart'] : null,
        );
    }
}
