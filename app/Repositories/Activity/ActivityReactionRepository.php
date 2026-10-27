<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\ActivityReactionKind;
use App\Domain\Activity\ActivityReactionSubject;
use App\Models\Activity\ActivityComment;
use App\Models\Activity\ActivityReaction;

class ActivityReactionRepository
{
    /**
     * @param  list<int>  $subjectIds
     * @return array<int, array{counts: array<string, int>, mine: ?string}>
     */
    public function summary(ActivityReactionSubject $subject, array $subjectIds, ?int $userId): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $rows = ActivityReaction::query()
            ->selectRaw('subject_id, kind, COUNT(*) as total')
            ->where('subject_type', $subject->value)
            ->whereIn('subject_id', $subjectIds)
            ->groupBy('subject_id', 'kind')
            ->get();

        $summary = [];
        foreach ($rows as $row) {
            $id = (int) $row->subject_id;
            $summary[$id]['counts'][$row->kind] = (int) $row->total;
            $summary[$id]['mine'] ??= null;
        }

        if ($userId !== null) {
            $mine = ActivityReaction::query()
                ->where('user_id', $userId)
                ->where('subject_type', $subject->value)
                ->whereIn('subject_id', $subjectIds)
                ->get();

            foreach ($mine as $reaction) {
                $id = (int) $reaction->subject_id;
                $summary[$id]['counts'] ??= [];
                $summary[$id]['mine'] = $reaction->kind;
            }
        }

        return $summary;
    }

    public function mine(int $userId, ActivityReactionSubject $subject, int $subjectId): ?string
    {
        $kind = ActivityReaction::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->where('subject_id', $subjectId)
            ->value('kind');

        return $kind === null ? null : (string) $kind;
    }

    public function put(int $userId, ActivityReactionSubject $subject, int $subjectId, ?ActivityReactionKind $kind): void
    {
        $key = [
            'user_id' => $userId,
            'subject_type' => $subject->value,
            'subject_id' => $subjectId,
        ];

        if ($kind === null) {
            ActivityReaction::query()->where($key)->delete();

            return;
        }

        ActivityReaction::query()->updateOrCreate($key, ['kind' => $kind->value]);
    }

    /**
     * @param  list<int>  $activityIds
     */
    public function forgetActivities(array $activityIds): void
    {
        if ($activityIds === []) {
            return;
        }

        $commentIds = ActivityComment::query()
            ->whereIn('activity_id', $activityIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        ActivityReaction::query()
            ->where('subject_type', ActivityReactionSubject::Activity->value)
            ->whereIn('subject_id', $activityIds)
            ->delete();

        $this->forget(ActivityReactionSubject::Comment, $commentIds);
    }

    /**
     * @param  list<int>  $subjectIds
     */
    public function forget(ActivityReactionSubject $subject, array $subjectIds): void
    {
        if ($subjectIds === []) {
            return;
        }

        ActivityReaction::query()
            ->where('subject_type', $subject->value)
            ->whereIn('subject_id', $subjectIds)
            ->delete();
    }
}
