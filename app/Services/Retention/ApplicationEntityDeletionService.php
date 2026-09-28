<?php

namespace App\Services\Retention;

use App\Domain\Retention\ApplicationEntityKind;
use App\Domain\Retention\ApplicationEntityRetention;
use App\Models\Users\User;
use App\Repositories\Retention\ApplicationEntityRetentionRepository;
use Illuminate\Support\Facades\DB;

class ApplicationEntityDeletionService
{
    public function __construct(
        private ApplicationEntityRetentionRepository $retentionRepository,
    ) {}

    public function hide(ApplicationEntityKind $kind, int $id, int $userId): void
    {
        DB::transaction(function () use ($kind, $id, $userId) {
            $descendants = $this->retentionRepository->liveDescendants($kind, $id);
            $this->retentionRepository->hideRoot($kind, $id, $userId);

            foreach ($descendants as [$childKind, $childIds]) {
                $this->retentionRepository->hideAsCascade($childKind, $childIds, $kind, $id);
            }
        });
    }

    public function restore(ApplicationEntityKind $kind, int $id): string
    {
        return DB::transaction(fn () => $this->retentionRepository->restoreRoot($kind, $id));
    }

    /**
     * @return list<array{kind: ApplicationEntityKind, id: int, name: string, deletedAt: \Carbon\CarbonInterface, purgeAt: \Carbon\CarbonInterface, deletedBy: string}>
     */
    public function deletedRoots(): array
    {
        $rows = $this->retentionRepository->deletedRoots();
        $userIds = collect($rows)->pluck('deletedByUserId')->filter()->unique()->all();
        $users = User::query()
            ->with('player')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return array_map(function (array $row) use ($users) {
            $user = $row['deletedByUserId'] !== null ? $users->get($row['deletedByUserId']) : null;

            return [
                'kind' => $row['kind'],
                'id' => $row['id'],
                'name' => $row['name'],
                'deletedAt' => $row['deletedAt'],
                'purgeAt' => $row['deletedAt']->copy()->addDays(ApplicationEntityRetention::DAYS),
                'deletedBy' => $user?->player?->name ?? $user?->email ?? '—',
            ];
        }, $rows);
    }

    public function purgeExpired(): int
    {
        $cutoff = ApplicationEntityRetention::cutoff();
        $purged = 0;

        $order = [
            ApplicationEntityKind::LeagueSeason,
            ApplicationEntityKind::Tournament,
            ApplicationEntityKind::League,
            ApplicationEntityKind::Season,
            ApplicationEntityKind::Organization,
        ];

        foreach ($order as $kind) {
            foreach ($this->retentionRepository->expiredRootIds($kind, $cutoff) as $id) {
                if ($this->retentionRepository->hasBlockingDescendant($kind, $id)) {
                    continue;
                }

                DB::transaction(fn () => $this->retentionRepository->forceDeleteRoot($kind, $id));
                $purged++;
            }
        }

        return $purged;
    }
}
