<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingHistoryRetention;
use App\Models\Training\TrainingChallengePublication;
use App\Models\Training\TrainingEventAttempt;
use App\Models\Training\TrainingHistoryWipe;
use App\Models\Training\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingHistoryWipeRepository
{
    public function createForUser(int $userId, CarbonImmutable $at): TrainingHistoryWipe
    {
        return TrainingHistoryWipe::query()->create([
            'user_id' => $userId,
            'wiped_at' => $at,
            'purge_after' => $at->addDays(TrainingHistoryRetention::DAYS),
            'restored_at' => null,
        ]);
    }

    public function attachOpenRows(int $userId, int $wipeId): void
    {
        TrainingSession::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereNull('wipe_id')
            ->update(['wipe_id' => $wipeId]);

        TrainingChallengePublication::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereNull('wipe_id')
            ->update(['wipe_id' => $wipeId]);

        TrainingEventAttempt::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereNull('wipe_id')
            ->update(['wipe_id' => $wipeId]);
    }

    public function latestOpenForUser(int $userId): ?TrainingHistoryWipe
    {
        return TrainingHistoryWipe::query()
            ->where('user_id', $userId)
            ->whereNull('restored_at')
            ->orderByDesc('id')
            ->first();
    }

    public function restore(TrainingHistoryWipe $wipe): void
    {
        DB::transaction(function () use ($wipe): void {
            TrainingSession::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->update(['wipe_id' => null]);
            TrainingChallengePublication::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->update(['wipe_id' => null]);
            TrainingEventAttempt::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->update(['wipe_id' => null]);

            $wipe->forceFill(['restored_at' => now()])->save();
        });
    }

    /**
     * @return Collection<int, TrainingHistoryWipe>
     */
    public function expiredOpen(CarbonImmutable $now): Collection
    {
        return TrainingHistoryWipe::query()
            ->whereNull('restored_at')
            ->where('purge_after', '<=', $now)
            ->orderBy('id')
            ->get();
    }

    public function purge(TrainingHistoryWipe $wipe): void
    {
        DB::transaction(function () use ($wipe): void {
            // Publikacje trzymają FK restrict na session_id — najpierw publikacje.
            TrainingChallengePublication::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->delete();
            TrainingSession::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->delete();
            TrainingEventAttempt::withoutGlobalScopes()
                ->where('wipe_id', $wipe->id)
                ->delete();
            $wipe->delete();
        });
    }

    public function forceDeleteAllForUser(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $wipeIds = TrainingHistoryWipe::query()
                ->where('user_id', $userId)
                ->pluck('id')
                ->all();

            TrainingChallengePublication::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->delete();
            TrainingSession::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->delete();
            TrainingEventAttempt::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->delete();

            if ($wipeIds !== []) {
                TrainingHistoryWipe::query()->whereIn('id', $wipeIds)->delete();
            }
        });
    }
}
