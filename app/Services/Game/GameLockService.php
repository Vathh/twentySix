<?php

namespace App\Services\Game;

use App\Enums\GameType;
use App\Models\Game\Game;
use App\Models\PlayoffGame\PlayoffGame;
use App\Repositories\Game\GameLegRepository;
use App\Repositories\Game\GameRepository;
use App\Repositories\Game\GameVisitRepository;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Support\GameScoring\GameScoringContext;
use DomainException;
use Illuminate\Support\Facades\DB;

class GameLockService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
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

    public function release(int $gameId, GameType $type): void
    {
        $context = match ($type) {
            GameType::GROUP => GameScoringContext::fromGroupGame(
                Game::query()->findOrFail($gameId),
            ),
            GameType::PLAYOFF => GameScoringContext::fromPlayoffGame(
                PlayoffGame::query()->findOrFail($gameId),
            ),
            GameType::QUICK_MATCH => throw new DomainException('Użyj endpointu quick-game.'),
        };

        if (! $this->canRelease($context)) {
            throw new DomainException('Nie można odblokować meczu — wprowadzono już wyniki.');
        }

        DB::transaction(function () use ($context, $gameId, $type) {
            $this->gameLegRepository->deleteForContext($context);

            $unlocked = match ($type) {
                GameType::GROUP => $this->gameRepository->tryUnlockInProgress($gameId),
                GameType::PLAYOFF => $this->playoffGameRepository->tryUnlockInProgress($gameId),
                GameType::QUICK_MATCH => false,
            };

            if (! $unlocked) {
                throw new DomainException('Mecz nie jest w trakcie sędziowania.');
            }
        });
    }

    private function canRelease(GameScoringContext $context): bool
    {
        $legs = $this->gameLegRepository->getForContext($context);

        if ($legs->contains(static fn ($leg) => $leg->finished_at !== null)) {
            return false;
        }

        $legIds = $legs->pluck('id')->all();

        return $this->gameVisitRepository->countActiveForGameLegs($legIds) === 0;
    }
}
