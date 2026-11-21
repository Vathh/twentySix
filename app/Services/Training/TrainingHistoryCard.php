<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;

final class TrainingHistoryCard
{
    /**
     * @return array<string, mixed>
     */
    public static function from(TrainingSession $session, bool $detail = false): array
    {
        $payload = [
            'id' => (int) $session->id,
            'clientSessionId' => $session->client_session_id,
            'kind' => $session->kind->value,
            'kindLabel' => $session->kind->singular(),
            'name' => self::name($session),
            'definitionId' => $session->definition_id,
            'exerciseKey' => $session->exercise_key,
            'completedAt' => $session->completed_at?->toIso8601String(),
            'score' => $session->score,
            'sync' => 'synced',
        ];

        if ($detail) {
            $payload['stats'] = $session->stats === [] || $session->stats === null
                ? (object) []
                : $session->stats;
        }

        return $payload;
    }

    private static function name(TrainingSession $session): string
    {
        if ($session->kind === TrainingKind::Exercise && is_string($session->exercise_key)) {
            return TrainingExerciseCatalog::label($session->exercise_key);
        }

        $definition = $session->relationLoaded('definition') ? $session->definition : null;
        if ($definition !== null && is_string($definition->name) && $definition->name !== '') {
            return $definition->name;
        }

        return $session->kind->singular();
    }
}
