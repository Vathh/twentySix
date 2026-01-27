<?php

namespace App\Services;

use App\DTO\MatchLegDTO;
use App\Repositories\MatchLegRepository;

class MatchLegService
{
    public function __construct(
        private MatchLegRepository $matchLegRepository
    )
    {
    }

    /**
     * @param MatchLegDTO[] $legs
     * @param int|null $gameId
     * @param int|null $playoffGameId
     * @param int|null $quickGameId
     * @return void
     */
    public function createMany(array $legs, ?int $gameId = null, ?int $playoffGameId = null, ?int $quickGameId = null): void
    {
        if (empty($legs)) {
            return;
        }

        $this->matchLegRepository->createMany($legs, $gameId, $playoffGameId, $quickGameId);
    }
}
