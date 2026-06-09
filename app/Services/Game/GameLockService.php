<?php

namespace App\Services\Game;

use App\Enums\GameType;
use App\Repositories\Game\GameRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use DomainException;

class GameLockService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
    ) {
    }

    public function lock(int $gameId, GameType $type): void
    {
        $locked = match ($type) {
            GameType::GROUP => $this->gameRepository->tryLockScheduled($gameId),
            GameType::PLAYOFF => $this->playoffGameRepository->tryLockScheduled($gameId),
            GameType::QUICK_MATCH => throw new DomainException('Użyj endpointu quick-game/inProgress.'),
        };

        if (! $locked) {
            throw new DomainException('Mecz jest już rozegrany lub sędziowany na innym tablecie.');
        }
    }
}
