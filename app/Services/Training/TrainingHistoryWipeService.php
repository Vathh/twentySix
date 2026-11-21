<?php

namespace App\Services\Training;

use App\Models\Users\User;
use App\Repositories\Training\TrainingHistoryWipeRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TrainingHistoryWipeService
{
    public function __construct(
        private TrainingHistoryWipeRepository $wipes,
    ) {}

    /**
     * @return array{wiped: bool, purgeAfter: string|null}
     */
    public function wipeForUser(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $at = CarbonImmutable::now();
            $wipe = $this->wipes->createForUser((int) $user->id, $at);
            $this->wipes->attachOpenRows((int) $user->id, (int) $wipe->id);

            return [
                'wiped' => true,
                'purgeAfter' => $wipe->purge_after?->toIso8601String(),
            ];
        });
    }

    public function restoreLatestForUser(int $userId): bool
    {
        $wipe = $this->wipes->latestOpenForUser($userId);
        if ($wipe === null) {
            return false;
        }
        $this->wipes->restore($wipe);

        return true;
    }

    public function purgeExpired(): int
    {
        $expired = $this->wipes->expiredOpen(CarbonImmutable::now());
        $count = 0;
        foreach ($expired as $wipe) {
            $this->wipes->purge($wipe);
            $count++;
        }

        return $count;
    }

    public function forceDeleteForAccount(User $user): void
    {
        $this->wipes->forceDeleteAllForUser((int) $user->id);
    }
}
