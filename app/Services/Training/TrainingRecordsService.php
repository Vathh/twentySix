<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use Carbon\CarbonImmutable;

class TrainingRecordsService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $best = [];

        $sessions = TrainingSession::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', [TrainingKind::Exercise->value, TrainingKind::Plan->value])
            ->orderBy('completed_at')
            ->get(['exercise_key', 'kind', 'score', 'stats', 'completed_at']);

        foreach ($sessions as $session) {
            if ($session->kind === TrainingKind::Exercise) {
                $this->consider($best, $session->exercise_key, $session->score, $session->completed_at);

                continue;
            }

            $items = is_array($session->stats) ? ($session->stats['items'] ?? null) : null;
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (($item['kind'] ?? null) !== 'exercise') {
                    continue;
                }
                if (($item['status'] ?? null) !== 'completed') {
                    continue;
                }
                $key = $item['exerciseKey'] ?? null;
                $score = $item['score'] ?? null;
                $this->consider($best, is_string($key) ? $key : null, $score, $session->completed_at);
            }
        }

        $rows = [];
        foreach ($best as $exerciseKey => $row) {
            $rows[] = [
                'exerciseKey' => $exerciseKey,
                'name' => TrainingExerciseCatalog::label($exerciseKey),
                'score' => $row['score'],
                'achievedAt' => $row['achievedAt'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @param  array<string, array{score: int, achievedAt: string}>  $best
     */
    private function consider(array &$best, ?string $exerciseKey, mixed $score, mixed $completedAt): void
    {
        if ($exerciseKey === null || TrainingExerciseCatalog::parameters($exerciseKey) === null) {
            return;
        }
        if (! is_int($score) && ! (is_string($score) && preg_match('/^-?\d+$/', $score) === 1)) {
            return;
        }
        $value = (int) $score;
        $at = $completedAt instanceof \DateTimeInterface
            ? CarbonImmutable::instance($completedAt)->toIso8601String()
            : CarbonImmutable::parse((string) $completedAt)->toIso8601String();

        if (! isset($best[$exerciseKey]) || $value > $best[$exerciseKey]['score']) {
            $best[$exerciseKey] = [
                'score' => $value,
                'achievedAt' => $at,
            ];
        }
    }
}
