<?php

namespace App\Domain\Training;

final class TrainingDefinitionRules
{
    public static function assertCanActivate(TrainingDefinitionStatus $status): void
    {
        if ($status !== TrainingDefinitionStatus::Inactive) {
            throw new TrainingException(
                'Aktywować można tylko nieaktywną definicję.',
                'training_definition_not_inactive',
            );
        }
    }

    public static function assertCompletable(TrainingDefinitionStatus $status): void
    {
        // Offline: ukończony plan/wyzwanie z lokalnej kopii wchodzi do historii
        // także gdy treść została w międzyczasie wyłączona albo anulowana.
        if (in_array($status, [
            TrainingDefinitionStatus::Active,
            TrainingDefinitionStatus::Inactive,
            TrainingDefinitionStatus::Cancelled,
        ], true)) {
            return;
        }

        throw new TrainingException(
            'Tej treści nie da się teraz zapisać.',
            'training_definition_not_active',
        );
    }

    public static function assertDraftEditable(TrainingDefinitionStatus $status, bool $published): void
    {
        if ($published || $status !== TrainingDefinitionStatus::Inactive) {
            throw new TrainingException(
                'Opublikowanej treści nie zmieniamy. Nowa wersja to nowa treść.',
                'training_definition_locked',
            );
        }
    }

    public static function assertCanDeactivate(TrainingDefinitionStatus $status): void
    {
        if ($status !== TrainingDefinitionStatus::Active) {
            throw new TrainingException(
                'Dezaktywować można tylko aktywną treść.',
                'training_definition_not_active',
            );
        }
    }

    public static function assertCanCancel(TrainingKind $kind, TrainingDefinitionStatus $status): void
    {
        if (! in_array($kind, [TrainingKind::Plan, TrainingKind::Challenge, TrainingKind::Event], true)) {
            throw new TrainingException(
                'Tę treść się usuwa, a nie anuluje.',
                'training_definition_invalid',
            );
        }

        self::assertStillOpen($status);
    }

    public static function assertCanRemove(TrainingKind $kind, TrainingDefinitionStatus $status): void
    {
        if ($kind !== TrainingKind::Event) {
            throw new TrainingException(
                'Plan i wyzwanie się anuluje. Ćwiczenie jest na stałej liście.',
                'training_definition_invalid',
            );
        }

        self::assertStillOpen($status);
    }

    private static function assertStillOpen(TrainingDefinitionStatus $status): void
    {
        if (! in_array($status, [TrainingDefinitionStatus::Inactive, TrainingDefinitionStatus::Active], true)) {
            throw new TrainingException(
                'Tej treści nie da się już zmienić.',
                'training_definition_locked',
            );
        }
    }

    public static function assertKnownExercise(string $exerciseKey): void
    {
        if (TrainingExerciseCatalog::parameters($exerciseKey) === null) {
            throw new TrainingException(
                'Wskazane ćwiczenie nie istnieje.',
                'training_exercise_not_found',
            );
        }
    }
}
