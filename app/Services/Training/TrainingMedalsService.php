<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingEventAttemptStatus;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingEventAttempt;
use App\Models\Training\TrainingEventDisqualification;
use App\Models\Users\User;

/**
 * Medale za miejsca 1–3 w zakończonych eventach treningowych.
 */
class TrainingMedalsService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $finished = TrainingDefinition::query()
            ->where('kind', TrainingKind::Event)
            ->where('status', TrainingDefinitionStatus::Finished)
            ->orderByDesc('finished_at')
            ->get();

        $medals = [];
        foreach ($finished as $event) {
            $dq = TrainingEventDisqualification::query()
                ->where('definition_id', $event->id)
                ->where('user_id', $user->id)
                ->whereNull('reinstated_at')
                ->exists();
            if ($dq) {
                continue;
            }

            $mine = TrainingEventAttempt::query()
                ->where('definition_id', $event->id)
                ->where('user_id', $user->id)
                ->where('status', TrainingEventAttemptStatus::Completed)
                ->orderByDesc('score')
                ->first();
            if ($mine === null) {
                continue;
            }

            $dqIds = TrainingEventDisqualification::query()
                ->where('definition_id', $event->id)
                ->whereNull('reinstated_at')
                ->pluck('user_id')
                ->all();

            $better = TrainingEventAttempt::query()
                ->where('definition_id', $event->id)
                ->where('status', TrainingEventAttemptStatus::Completed)
                ->where('score', '>', $mine->score)
                ->when($dqIds !== [], fn ($q) => $q->whereNotIn('user_id', $dqIds))
                ->pluck('user_id')
                ->unique()
                ->count();

            $place = $better + 1;
            $color = match ($place) {
                1 => 'gold',
                2 => 'silver',
                3 => 'bronze',
                default => null,
            };
            if ($color === null) {
                continue;
            }

            $medals[] = [
                'id' => 'event_'.$event->id,
                'name' => 'Medal',
                'placeholder' => false,
                'eventName' => $event->name,
                'color' => $color,
                'place' => $place,
                'awardedAt' => $event->finished_at?->toIso8601String(),
            ];
        }

        if ($medals === []) {
            return [[
                'id' => 'medal_slot',
                'name' => 'Medal',
                'placeholder' => true,
                'eventName' => null,
                'color' => null,
                'awardedAt' => null,
            ]];
        }

        return $medals;
    }
}
