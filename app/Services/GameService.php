<?php

namespace App\Services;

use App\Repositories\GameRepository;

class GameService
{

    public function __construct(
        private GameRepository $gameRepository,
    )
    {
    }

    public function setStatusInProgress(int $gameId): void
    {
        $this->gameRepository->setStatusInProgress($gameId);
    }
}
