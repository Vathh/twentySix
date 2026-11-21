<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TrainingSessionRepository
{
    public function findForUser(int $userId, string $clientSessionId): ?TrainingSession
    {
        return TrainingSession::query()
            ->where('user_id', $userId)
            ->where('client_session_id', $clientSessionId)
            ->first();
    }

    public function findOwned(int $userId, int $id): ?TrainingSession
    {
        return TrainingSession::query()
            ->with('definition')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();
    }

    public function countCompletedFor(int $userId, int $definitionId): int
    {
        return TrainingSession::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->count();
    }

    /**
     * @param  list<TrainingKind>|null  $kinds
     * @return Collection<int, TrainingSession>
     */
    public function historyPage(
        int $userId,
        ?array $kinds,
        ?CarbonInterface $since,
        ?CarbonInterface $beforeCompletedAt,
        ?int $beforeId,
        int $limit,
    ): Collection {
        $query = TrainingSession::query()
            ->with('definition')
            ->where('user_id', $userId)
            ->orderByDesc('completed_at')
            ->orderByDesc('id');

        if ($kinds !== null && $kinds !== []) {
            $query->whereIn('kind', array_map(
                static fn (TrainingKind $kind): string => $kind->value,
                $kinds,
            ));
        }

        if ($since !== null) {
            $query->where('completed_at', '>=', $since);
        }

        if ($beforeCompletedAt !== null && $beforeId !== null) {
            $query->where(function ($inner) use ($beforeCompletedAt, $beforeId): void {
                $inner->where('completed_at', '<', $beforeCompletedAt)
                    ->orWhere(function ($sameDay) use ($beforeCompletedAt, $beforeId): void {
                        $sameDay->where('completed_at', '=', $beforeCompletedAt)
                            ->where('id', '<', $beforeId);
                    });
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * Historia prób jednego wyzwania: sort newest albo best.
     *
     * @return array{items: Collection<int, TrainingSession>, nextCursor: array{beforeCompletedAt: string, beforeId: int}|null}
     */
    public function challengeAttemptsPage(
        int $userId,
        int $definitionId,
        string $sort,
        ?int $days,
        ?string $beforeCompletedAt,
        ?int $beforeId,
        int $limit,
    ): array {
        $query = TrainingSession::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->where('kind', TrainingKind::Challenge);

        if ($days === 7 || $days === 30 || $days === 90) {
            $query->where('completed_at', '>=', now()->subDays($days));
        }

        if ($sort === 'best') {
            $query->orderByDesc('score')->orderByDesc('completed_at')->orderByDesc('id');
            if ($beforeCompletedAt !== null && $beforeId !== null) {
                $before = TrainingSession::query()->find($beforeId);
                if ($before instanceof TrainingSession) {
                    $query->where(function ($inner) use ($before): void {
                        $inner->where('score', '<', $before->score)
                            ->orWhere(function ($same) use ($before): void {
                                $same->where('score', '=', $before->score)
                                    ->where(function ($time) use ($before): void {
                                        $time->where('completed_at', '<', $before->completed_at)
                                            ->orWhere(function ($tie) use ($before): void {
                                                $tie->where('completed_at', '=', $before->completed_at)
                                                    ->where('id', '<', $before->id);
                                            });
                                    });
                            });
                    });
                }
            }
        } else {
            $query->orderByDesc('completed_at')->orderByDesc('id');
            if ($beforeCompletedAt !== null && $beforeId !== null) {
                $query->where(function ($inner) use ($beforeCompletedAt, $beforeId): void {
                    $inner->where('completed_at', '<', $beforeCompletedAt)
                        ->orWhere(function ($sameDay) use ($beforeCompletedAt, $beforeId): void {
                            $sameDay->where('completed_at', '=', $beforeCompletedAt)
                                ->where('id', '<', $beforeId);
                        });
                });
            }
        }

        $items = $query->limit($limit + 1)->get();
        $nextCursor = null;
        if ($items->count() > $limit) {
            $items = $items->take($limit);
            $last = $items->last();
            if ($last instanceof TrainingSession) {
                $nextCursor = [
                    'beforeCompletedAt' => $last->completed_at?->toIso8601String() ?? '',
                    'beforeId' => (int) $last->id,
                ];
            }
        }

        return [
            'items' => $items,
            'nextCursor' => $nextCursor,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    public function create(
        int $userId,
        string $clientSessionId,
        TrainingKind $kind,
        ?int $definitionId,
        ?string $exerciseKey,
        CarbonInterface $completedAt,
        ?int $score,
        array $stats,
    ): TrainingSession {
        return TrainingSession::query()->create([
            'user_id' => $userId,
            'client_session_id' => $clientSessionId,
            'definition_id' => $definitionId,
            'exercise_key' => $exerciseKey,
            'kind' => $kind,
            'completed_at' => $completedAt,
            'score' => $score,
            'stats' => $stats,
        ]);
    }
}
