<?php

namespace App\Repositories\Training;

use App\Models\Training\TrainingChallengePublication;
use Illuminate\Support\Collection;

class TrainingChallengePublicationRepository
{
    public function findForUser(int $userId, int $definitionId): ?TrainingChallengePublication
    {
        return TrainingChallengePublication::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->first();
    }

    public function upsert(
        int $userId,
        int $definitionId,
        int $sessionId,
        int $score,
    ): TrainingChallengePublication {
        $existing = $this->findForUser($userId, $definitionId);
        if ($existing instanceof TrainingChallengePublication) {
            $existing->session_id = $sessionId;
            $existing->score = $score;
            $existing->published_at = now();
            $existing->save();

            return $existing;
        }

        return TrainingChallengePublication::query()->create([
            'user_id' => $userId,
            'definition_id' => $definitionId,
            'session_id' => $sessionId,
            'score' => $score,
            'published_at' => now(),
        ]);
    }

    public function deleteForUser(int $userId, int $definitionId): bool
    {
        return TrainingChallengePublication::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->delete() > 0;
    }

    /**
     * @return Collection<int, TrainingChallengePublication>
     */
    public function rankingPage(int $definitionId, int $limit, int $offset): Collection
    {
        return TrainingChallengePublication::query()
            ->with('user')
            ->where('definition_id', $definitionId)
            ->orderByDesc('score')
            ->orderBy('user_id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Wszystkie publikacje do miejsc konkursowych (score DESC, name ASC).
     *
     * @return Collection<int, TrainingChallengePublication>
     */
    public function allForRanking(int $definitionId): Collection
    {
        return TrainingChallengePublication::query()
            ->with(['user.player'])
            ->where('definition_id', $definitionId)
            ->get()
            ->sort(function (TrainingChallengePublication $a, TrainingChallengePublication $b): int {
                if ($a->score !== $b->score) {
                    return $b->score <=> $a->score;
                }
                $nameA = mb_strtolower((string) ($a->user?->player?->name ?? $a->user?->name ?? ''));
                $nameB = mb_strtolower((string) ($b->user?->player?->name ?? $b->user?->name ?? ''));

                return $nameA <=> $nameB;
            })
            ->values();
    }

    public function countFor(int $definitionId): int
    {
        return TrainingChallengePublication::query()
            ->where('definition_id', $definitionId)
            ->count();
    }
}
