<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;
use Carbon\CarbonInterface;

class TrainingSessionRepository
{
    public function findForUser(int $userId, string $clientSessionId): ?TrainingSession
    {
        return TrainingSession::query()
            ->where('user_id', $userId)
            ->where('client_session_id', $clientSessionId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    public function create(
        int $userId,
        string $clientSessionId,
        TrainingKind $kind,
        ?int $definitionId,
        ?string $exerciseKey,
        CarbonInterface $completedAt,
        ?int $score,
        array $stats,
    ): TrainingSession {
        return TrainingSession::query()->create([
            'user_id' => $userId,
            'client_session_id' => $clientSessionId,
            'definition_id' => $definitionId,
            'exercise_key' => $exerciseKey,
            'kind' => $kind,
            'completed_at' => $completedAt,
            'score' => $score,
            'stats' => $stats,
        ]);
    }
}
