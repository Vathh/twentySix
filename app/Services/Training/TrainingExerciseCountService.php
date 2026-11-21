<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingCompletedExerciseCount;
use App\Models\Training\TrainingSession;
use App\Models\Users\User;

class TrainingExerciseCountService
{
    public function forUser(User $user): int
    {
        return $this->sumSessions(
            TrainingSession::query()->where('user_id', $user->id)->get(['kind', 'stats'])
        );
    }

    public function globalTotal(): int
    {
        return $this->sumSessions(
            TrainingSession::query()->get(['kind', 'stats'])
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TrainingSession>  $sessions
     */
    private function sumSessions($sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            $total += TrainingCompletedExerciseCount::forKind(
                $session->kind,
                is_array($session->stats) ? $session->stats : null,
            );
        }

        return $total;
    }
}
