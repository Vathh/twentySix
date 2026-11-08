<?php

namespace App\Repositories\Activity;

use App\Domain\Activity\FollowSubject;
use App\Models\Activity\Follow;

class FollowRepository
{
    public function follow(int $userId, FollowSubject $subject, int $subjectId): void
    {
        Follow::query()->firstOrCreate([
            'user_id' => $userId,
            'subject_type' => $subject->value,
            'subject_key' => (string) $subjectId,
        ]);
    }

    public function unfollow(int $userId, FollowSubject $subject, int $subjectId): void
    {
        Follow::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->where('subject_key', (string) $subjectId)
            ->delete();
    }

    public function followEachOther(int $userId, int $otherUserId): void
    {
        $this->follow($userId, FollowSubject::User, $otherUserId);
        $this->follow($otherUserId, FollowSubject::User, $userId);
    }

    public function unfollowEachOther(int $userId, int $otherUserId): void
    {
        $this->unfollow($userId, FollowSubject::User, $otherUserId);
        $this->unfollow($otherUserId, FollowSubject::User, $userId);
    }

    public function isFollowing(int $userId, FollowSubject $subject, int $subjectId): bool
    {
        return Follow::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->where('subject_key', (string) $subjectId)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function ids(int $userId, FollowSubject $subject): array
    {
        return Follow::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subject->value)
            ->pluck('subject_key')
            ->map(fn ($key) => (int) $key)
            ->all();
    }
}
