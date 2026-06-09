<?php

namespace App\DTO\GameScoring;

class RecordVisitDTO
{
    public function __construct(
        public int $playerId,
        public int $score,
        public int $remainingBefore,
        public int $remainingAfter,
        public int $dartsInVisit,
        public bool $closedLeg,
        public bool $bust,
        public string $clientVisitId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            playerId: (int) $data['playerId'],
            score: (int) $data['score'],
            remainingBefore: (int) $data['remainingBefore'],
            remainingAfter: (int) $data['remainingAfter'],
            dartsInVisit: (int) ($data['dartsInVisit'] ?? 3),
            closedLeg: (bool) ($data['closedLeg'] ?? false),
            bust: (bool) ($data['bust'] ?? false),
            clientVisitId: (string) $data['clientVisitId'],
        );
    }
}
