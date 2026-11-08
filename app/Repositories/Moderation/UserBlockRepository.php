<?php

namespace App\Repositories\Moderation;

use App\Models\Moderation\UserBlock;
use Illuminate\Support\Collection;

class UserBlockRepository
{
    /**
     * @return list<int>
     */
    public function blockedIds(int $blockerId): array
    {
        return UserBlock::query()
            ->where('blocker_id', $blockerId)
            ->pluck('blocked_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        return UserBlock::query()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }

    public function block(int $blockerId, int $blockedId): void
    {
        UserBlock::query()->firstOrCreate([
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
        ]);
    }

    public function unblock(int $blockerId, int $blockedId): void
    {
        UserBlock::query()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->delete();
    }

    /**
     * @return Collection<int, UserBlock>
     */
    public function listFor(int $blockerId): Collection
    {
        return UserBlock::query()
            ->with('blocked.player')
            ->where('blocker_id', $blockerId)
            ->orderByDesc('id')
            ->get();
    }
}
