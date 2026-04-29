<?php

namespace App\Services\PointScheme;

use App\Domain\Tournament\PointSchemeDomain;
use App\Models\PointScheme;
use App\Repositories\PointScheme\PointSchemeRepository;

class PointSchemeService
{

    public function __construct(
        private PointSchemeRepository $pointSchemeRepository,
    )
    {
    }

    public function findByPlayersAmount(int $playersAmount): PointSchemeDomain
    {
        return $this->pointSchemeRepository->findAll()->first(function (PointSchemeDomain $pointScheme) use ($playersAmount) {
                                return $playersAmount >= $pointScheme->minPlayers && $playersAmount <= $pointScheme->maxPlayers;
                            });
    }
}











