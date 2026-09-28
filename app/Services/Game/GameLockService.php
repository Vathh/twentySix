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
use App\Support\Http\DomainExceptionHttp;
use DomainException;
use Illuminate\Support\Facades\DB;

class GameLockService
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayoffGameRepository $playoffGameRepository,
        private GameLegRepository $gameLegRepository,
        private GameVisitRepository $gameVisitRepository,
    ) {}

    public function lock(int $gameId, GameType $type, int $tokenId): void
    {
        $locked = match ($type) {
            GameType::GROUP => $this->gameRepository->tryLockScheduled($gameId, $tokenId),
            GameType::PLAYOFF => $this->playoffGameRepository->tryLockScheduled($gameId, $tokenId),
            GameType::QUICK_MATCH => throw new DomainException(
                'Quick game blokuje się przez sesję FFA lobby, nie przez lock turniejowy.',
                DomainExceptionHttp::CONFLICT,
            ),
        };

        if (! $locked) {
            throw new DomainException(
                'Mecz jest już rozegrany lub sędziowany na innym urządzeniu.',
                DomainExceptionHttp::CONFLICT,
            );
        }
    }

    public function renew(int $gameId, GameType $type, int $tokenId): void
    {
        $renewed = match ($type) {
            GameType::GROUP => $this->gameRepository->tryRenewLock($gameId, $tokenId),
            GameType::PLAYOFF => $this->playoffGameRepository->tryRenewLock($gameId, $tokenId),
            GameType::QUICK_MATCH => throw new DomainException(
                'Quick game nie odnawia locku turniejowego.',
                DomainExceptionHttp::CONFLICT,
            ),
        };

        if (! $renewed) {
            throw new DomainException(
                'Mecz jest sędziowany na innym urządzeniu.',
                DomainExceptionHttp::CONFLICT,
            );
        }
    }

    public function release(int $gameId, GameType $type, int $tokenId): void
    {
        $context = match ($type) {
            GameType::GROUP => GameScoringContext::fromGroupGame(
                $this->gameRepository->findModel($gameId),
            ),
            GameType::PLAYOFF => GameScoringContext::fromPlayoffGame(
                $this->playoffGameRepository->findModel($gameId),
            ),
            GameType::QUICK_MATCH => throw new DomainException(
                'Quick game zwalnia się z sesją FFA, nie przez release turniejowy.',
                DomainExceptionHttp::CONFLICT,
            ),
        };

        if (! $this->canRelease($context)) {
            $this->expireKeptProgress($gameId, $type, $tokenId);

            return;
        }

        DB::transaction(function () use ($context, $gameId, $type, $tokenId) {
            $this->gameLegRepository->deleteForContext($context);

            $unlocked = match ($type) {
                GameType::GROUP => $this->gameRepository->tryUnlockInProgress($gameId, $tokenId),
                GameType::PLAYOFF => $this->playoffGameRepository->tryUnlockInProgress($gameId, $tokenId),
                GameType::QUICK_MATCH => false,
            };

            if (! $unlocked) {
                $inProgress = match ($type) {
                    GameType::GROUP => $this->gameRepository->isInProgress($gameId),
                    GameType::PLAYOFF => $this->playoffGameRepository->isInProgress($gameId),
                    GameType::QUICK_MATCH => false,
                };

                throw new DomainException(
                    $inProgress
                        ? 'Mecz jest sędziowany na innym urządzeniu.'
                        : 'Mecz nie jest w trakcie sędziowania.',
                    DomainExceptionHttp::CONFLICT,
                );
            }
        });
    }

    public function assertHolder(Game|PlayoffGame $game, ?int $tokenId): void
    {
        if ($game->scoring_token_id === null) {
            return;
        }

        $expires = $game->scoring_lock_expires_at;
        $fresh = $expires !== null && $expires->isFuture();
        if ($tokenId !== null && (int) $game->scoring_token_id === $tokenId && $fresh) {
            return;
        }

        throw new DomainException(
            'Mecz jest sędziowany na innym urządzeniu.',
            DomainExceptionHttp::FORBIDDEN,
        );
    }

    private function expireKeptProgress(int $gameId, GameType $type, int $tokenId): void
    {
        $expired = match ($type) {
            GameType::GROUP => $this->gameRepository->tryExpireLock($gameId, $tokenId),
            GameType::PLAYOFF => $this->playoffGameRepository->tryExpireLock($gameId, $tokenId),
            GameType::QUICK_MATCH => false,
        };

        if ($expired) {
            return;
        }

        $inProgress = match ($type) {
            GameType::GROUP => $this->gameRepository->isInProgress($gameId),
            GameType::PLAYOFF => $this->playoffGameRepository->isInProgress($gameId),
            GameType::QUICK_MATCH => false,
        };

        throw new DomainException(
            $inProgress
                ? 'Mecz jest sędziowany na innym urządzeniu.'
                : 'Mecz nie jest w trakcie sędziowania.',
            DomainExceptionHttp::CONFLICT,
        );
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
