<?php

namespace App\Repositories\Push;

use App\Models\Push\AdminPushDispatch;
use Illuminate\Support\Collection;

class AdminPushDispatchRepository
{
    public function create(
        int $adminId,
        string $title,
        string $body,
        string $audience,
        int $recipientCount,
        bool $testOnly,
    ): AdminPushDispatch {
        return AdminPushDispatch::query()->create([
            'admin_id' => $adminId,
            'title' => $title,
            'body' => $body,
            'audience' => $audience,
            'recipient_count' => $recipientCount,
            'test_only' => $testOnly,
            'sent_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, AdminPushDispatch>
     */
    public function latest(int $limit = 50): Collection
    {
        return AdminPushDispatch::query()
            ->with('admin.player')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
