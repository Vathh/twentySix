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

    public function deleteOwnedAndKeepOthersReplies(int $userId): void
    {
        $ids = ActivityComment::query()
            ->where('user_id', $userId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        ActivityComment::query()
            ->whereIn('parent_id', $ids)
            ->where('user_id', '!=', $userId)
            ->update(['parent_id' => null]);

        $this->reactions->forget(ActivityReactionSubject::Comment, $ids);

        ActivityComment::query()
            ->where('user_id', $userId)
            ->whereNotNull('parent_id')
            ->delete();

        ActivityComment::query()
            ->where('user_id', $userId)
            ->delete();
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
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function bodiesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $bodies = [];
        foreach (ActivityComment::query()->whereIn('id', $ids)->pluck('body', 'id') as $id => $body) {
            $bodies[(int) $id] = (string) $body;
        }

        return $bodies;
    }

    /**
     * Komentarze autorów z $excludedUserIds oraz odpowiedzi pod ich komentarzami nie wchodzą do liczby.
     *
     * @param  list<int>  $activityIds
     * @param  list<int>  $excludedUserIds
     * @return array<int, int>
     */
    public function countsFor(array $activityIds, array $excludedUserIds = []): array
    {
        if ($activityIds === []) {
            return [];
        }

        $query = ActivityComment::query()
            ->selectRaw('activity_id, COUNT(*) as total')
            ->whereIn('activity_id', $activityIds);

        if ($excludedUserIds !== []) {
            $query->whereNotIn('user_id', $excludedUserIds)
                ->where(function ($visible) use ($excludedUserIds) {
                    $visible->whereNull('parent_id')
                        ->orWhereNotExists(function ($parent) use ($excludedUserIds) {
                            $parent->selectRaw('1')
                                ->from('activity_comments as parents')
                                ->whereColumn('parents.id', 'activity_comments.parent_id')
                                ->whereIn('parents.user_id', $excludedUserIds);
                        });
                });
        }

        return $query
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
