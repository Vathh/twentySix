<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingCompletedExerciseCount;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;
use Carbon\CarbonImmutable;

/**
 * Dzienna aktywność treningowa: liczba ukończonych pojedynczych ćwiczeń.
 * Plan z 5 ćwiczeniami = 5 w dniu ukończenia planu.
 */
class TrainingActivityService
{
    public const DEFAULT_DAYS = 90;

    /**
     * @return array{days: int, points: list<array{date: string, count: int}>}
     */
    public function forUser(User $user, int $days = self::DEFAULT_DAYS): array
    {
        $span = max(1, min(90, $days));
        $to = CarbonImmutable::now('UTC')->startOfDay();
        $from = $to->subDays($span - 1);

        $buckets = [];
        $cursor = $from;
        while ($cursor->lessThanOrEqualTo($to)) {
            $buckets[$cursor->toDateString()] = 0;
            $cursor = $cursor->addDay();
        }

        $sessions = TrainingSession::query()
            ->where('user_id', $user->id)
            ->where('completed_at', '>=', $from->startOfDay())
            ->where('completed_at', '<=', $to->endOfDay())
            ->get(['kind', 'stats', 'completed_at']);

        foreach ($sessions as $session) {
            if ($session->completed_at === null) {
                continue;
            }
            $day = CarbonImmutable::instance($session->completed_at)->timezone('UTC')->toDateString();
            if (! array_key_exists($day, $buckets)) {
                continue;
            }
            $buckets[$day] += TrainingCompletedExerciseCount::forKind(
                $session->kind,
                is_array($session->stats) ? $session->stats : null,
            );
        }

        $points = [];
        foreach ($buckets as $date => $count) {
            $points[] = [
                'date' => $date,
                'count' => $count,
            ];
        }

        return [
            'days' => $span,
            'points' => $points,
        ];
    }
}
