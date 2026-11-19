<?php

namespace App\Domain\Training;

final class TrainingFavoriteRules
{
    public static function assertFavoritable(TrainingKind $kind, TrainingDefinitionStatus $status): void
    {
        if (! in_array($kind, [TrainingKind::Exercise, TrainingKind::Plan, TrainingKind::Challenge], true)) {
            throw new TrainingException(
                'Do ulubionych wchodzą ćwiczenia, plany i wyzwania.',
                'training_favorite_not_allowed',
            );
        }

        if ($status !== TrainingDefinitionStatus::Active) {
            throw new TrainingException(
                'Do ulubionych dodajesz tylko aktywną treść.',
                'training_favorite_inactive',
            );
        }
    }
}
