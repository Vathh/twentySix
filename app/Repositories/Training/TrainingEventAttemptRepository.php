<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingEventAttemptStatus;
use App\Models\Training\TrainingEventAttempt;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TrainingEventAttemptRepository
{
    public function findOpenForUser(int $userId, int $definitionId): ?TrainingEventAttempt
    {
        return TrainingEventAttempt::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::InProgress)
            ->orderByDesc('id')
            ->first();
    }

    public function findOpenAnywhere(int $userId): ?TrainingEventAttempt
    {
        return TrainingEventAttempt::query()
            ->where('user_id', $userId)
            ->where('status', TrainingEventAttemptStatus::InProgress)
            ->orderByDesc('id')
            ->first();
    }

    public function findByClient(int $userId, string $clientAttemptId): ?TrainingEventAttempt
    {
        return TrainingEventAttempt::query()
            ->where('user_id', $userId)
            ->where('client_attempt_id', $clientAttemptId)
            ->first();
    }

    public function countConsumedInWindow(int $userId, int $definitionId, string $windowKey): int
    {
        return TrainingEventAttempt::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->where('window_key', $windowKey)
            ->whereIn('status', [
                TrainingEventAttemptStatus::Completed,
                TrainingEventAttemptStatus::Voided,
                TrainingEventAttemptStatus::InProgress,
            ])
            ->count();
    }

    public function createInProgress(
        int $userId,
        int $definitionId,
        string $clientAttemptId,
        string $windowKey,
        CarbonInterface $startedAt,
        CarbonInterface $expiresAt,
    ): TrainingEventAttempt {
        return TrainingEventAttempt::query()->create([
            'user_id' => $userId,
            'definition_id' => $definitionId,
            'client_attempt_id' => $clientAttemptId,
            'status' => TrainingEventAttemptStatus::InProgress,
            'window_key' => $windowKey,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    public function markCompleted(TrainingEventAttempt $attempt, int $score, array $stats, CarbonInterface $at): TrainingEventAttempt
    {
        $attempt->status = TrainingEventAttemptStatus::Completed;
        $attempt->score = $score;
        $attempt->stats = $stats;
        $attempt->completed_at = $at;
        $attempt->save();

        return $attempt;
    }

    public function markVoided(TrainingEventAttempt $attempt, string $reason, CarbonInterface $at): TrainingEventAttempt
    {
        $attempt->status = TrainingEventAttemptStatus::Voided;
        $attempt->void_reason = $reason;
        $attempt->voided_at = $at;
        $attempt->save();

        return $attempt;
    }

    /**
     * @return Collection<int, TrainingEventAttempt>
     */
    public function completedForEvent(int $definitionId): Collection
    {
        return TrainingEventAttempt::query()
            ->with('user.player')
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::Completed)
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->get();
    }

    /**
     * @return Collection<int, TrainingEventAttempt>
     */
    public function openForEvent(int $definitionId): Collection
    {
        return TrainingEventAttempt::query()
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::InProgress)
            ->get();
    }

    public function hasOpenForEvent(int $definitionId): bool
    {
        return TrainingEventAttempt::query()
            ->where('definition_id', $definitionId)
            ->where('status', TrainingEventAttemptStatus::InProgress)
            ->exists();
    }
}
