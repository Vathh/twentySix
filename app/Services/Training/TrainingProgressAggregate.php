<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingKind;
use App\Domain\Training\TrainingMetricExtractor;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use Carbon\CarbonImmutable;

/**
 * Zbiera metryki z ukończonych sesji użytkownika (bez wipe).
 */
final class TrainingProgressAggregate
{
    /**
     * @return array{
     *   scoringTotal: int,
     *   scoringApproaches: int,
     *   scoringVisitsAtLeast60: int,
     *   doublesAttempts: int,
     *   doublesHits: int,
     *   checkoutsClosed: int,
     *   checkoutAttempts: int,
     *   regularitySessions: int,
     *   challengeBest: int|null,
     *   challengeRecentScores: list<int>
     * }
     */
    public function forUser(User $user): array
    {
        $sessions = TrainingSession::query()
            ->where('user_id', $user->id)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $agg = [
            'scoringTotal' => 0,
            'scoringApproaches' => 0,
            'scoringVisitsAtLeast60' => 0,
            'doublesAttempts' => 0,
            'doublesHits' => 0,
            'checkoutsClosed' => 0,
            'checkoutAttempts' => 0,
            'regularitySessions' => 0,
            'challengeBest' => null,
            'challengeRecentScores' => [],
        ];

        $since = CarbonImmutable::now()->subDays(30);
        $challengeScores = [];

        foreach ($sessions as $session) {
            if ($session->completed_at !== null && $session->completed_at->greaterThanOrEqualTo($since)) {
                $agg['regularitySessions']++;
            }

            if ($session->kind === TrainingKind::Challenge) {
                $score = is_int($session->score) ? $session->score : null;
                if ($score !== null) {
                    $challengeScores[] = $score;
                    if ($agg['challengeBest'] === null || $score > $agg['challengeBest']) {
                        $agg['challengeBest'] = $score;
                    }
                }
            }

            $this->absorbSession($agg, $session);
        }

        $agg['challengeRecentScores'] = array_slice($challengeScores, 0, 5);

        return $agg;
    }

    /**
     * @param  array<string, mixed>  $agg
     */
    private function absorbSession(array &$agg, TrainingSession $session): void
    {
        if ($session->kind === TrainingKind::Exercise && is_string($session->exercise_key)) {
            $this->merge($agg, TrainingMetricExtractor::fromExercise(
                $session->exercise_key,
                is_array($session->stats) ? $session->stats : null,
            ));

            return;
        }

        if ($session->kind !== TrainingKind::Plan && $session->kind !== TrainingKind::Event) {
            return;
        }

        $items = is_array($session->stats) ? ($session->stats['items'] ?? null) : null;
        if (! is_array($items)) {
            return;
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
            if (! is_string($key)) {
                continue;
            }
            $stats = is_array($item['stats'] ?? null) ? $item['stats'] : null;
            if ($stats === null && is_array($item['aims'] ?? null)) {
                $stats = ['aims' => $item['aims']];
            }
            $this->merge($agg, TrainingMetricExtractor::fromExercise($key, $stats));
        }
    }

    /**
     * @param  array<string, mixed>  $agg
     * @param  array<string, int>  $part
     */
    private function merge(array &$agg, array $part): void
    {
        foreach ([
            'scoringTotal',
            'scoringApproaches',
            'scoringVisitsAtLeast60',
            'doublesAttempts',
            'doublesHits',
            'checkoutsClosed',
            'checkoutAttempts',
        ] as $key) {
            $agg[$key] += $part[$key];
        }
    }
}
