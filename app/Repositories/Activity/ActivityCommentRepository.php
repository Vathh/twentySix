<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\ActivityReactionSubject;
use App\Models\Activity\ActivityComment;
use Illuminate\Support\Collection;

class ActivityCommentRepository
{
    public function __construct(private ActivityReactionRepository $reactions) {}

    /**
     * @return Collection<int, ActivityComment>
     */
    public function forActivity(int $activityId): Collection
    {
        return ActivityComment::query()
            ->with('user.player')
            ->where('activity_id', $activityId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?ActivityComment
    {
        return ActivityComment::query()
            ->with(['user.player', 'activity.actorPlayer.user'])
            ->find($id);
    }

    public function create(int $activityId, int $userId, ?int $parentId, string $body): ActivityComment
    {
        $comment = ActivityComment::query()->create([
            'activity_id' => $activityId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
        ]);

        return $comment->load('user.player');
    }

    public function deleteWithReplies(ActivityComment $comment): void
    {
        $ids = ActivityComment::query()
            ->where('id', $comment->id)
            ->orWhere('parent_id', $comment->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->reactions->forget(ActivityReactionSubject::Comment, $ids);
        $comment->delete();
    }

    /**
     * @param  list<int>  $activityIds
     * @return array<int, int>
     */
    public function countsFor(array $activityIds): array
    {
        if ($activityIds === []) {
            return [];
        }

        return ActivityComment::query()
            ->selectRaw('activity_id, COUNT(*) as total')
            ->whereIn('activity_id', $activityIds)
            ->groupBy('activity_id')
            ->pluck('total', 'activity_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function countFor(int $activityId): int
    {
        return ActivityComment::query()->where('activity_id', $activityId)->count();
    }
}
