<?php

namespace App\Support\GameScoring;

use App\Enums\GameStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ScoringLock
{
    public const LEASE_SECONDS = 60;

    public static function until(): DateTimeInterface
    {
        return now()->addSeconds(self::LEASE_SECONDS);
    }

    public static function releasedAt(): DateTimeInterface
    {
        return now()->subMinute();
    }

    /**
     * Mecz wolny albo trzymany przez tę sesję / z wygasłą dzierżawą.
     */
    public static function constrainClaimable(EloquentBuilder|QueryBuilder $query, int $tokenId): void
    {
        $now = now();
        $query->whereIn('status', [GameStatus::SCHEDULED, GameStatus::IN_PROGRESS])
            ->where(function ($claim) use ($tokenId, $now) {
                $claim->where('status', GameStatus::SCHEDULED)
                    ->orWhereNull('scoring_token_id')
                    ->orWhere('scoring_token_id', $tokenId)
                    ->orWhereNull('scoring_lock_expires_at')
                    ->orWhere('scoring_lock_expires_at', '<=', $now);
            });
    }

    /**
     * Lista sędziego: oczekujące oraz w trakcie, których nikt właśnie nie trzyma.
     */
    public static function constrainAvailable(EloquentBuilder|QueryBuilder $query, ?int $tokenId): void
    {
        $now = now();
        $query->where(function ($outer) use ($tokenId, $now) {
            $outer->where('status', GameStatus::SCHEDULED)
                ->orWhere(function ($live) use ($tokenId, $now) {
                    $live->where('status', GameStatus::IN_PROGRESS)
                        ->where(function ($free) use ($tokenId, $now) {
                            $free->whereNull('scoring_token_id')
                                ->orWhereNull('scoring_lock_expires_at')
                                ->orWhere('scoring_lock_expires_at', '<=', $now);
                            if ($tokenId !== null) {
                                $free->orWhere('scoring_token_id', $tokenId);
                            }
                        });
                });
        });
    }

    public static function isHeldByOther(?int $holderTokenId, ?string $expiresAt, ?int $viewerTokenId): bool
    {
        if ($holderTokenId === null || $expiresAt === null) {
            return false;
        }

        if ($viewerTokenId !== null && $holderTokenId === $viewerTokenId) {
            return false;
        }

        return now()->lt($expiresAt);
    }
}
