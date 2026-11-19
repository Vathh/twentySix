<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingChallengeCatalog;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;

final class TrainingCatalogCard
{
    /**
     * @return array<string, mixed>
     */
    public static function from(TrainingDefinition $definition, bool $favorite): array
    {
        return [
            'id' => (int) $definition->id,
            'kind' => $definition->kind->value,
            'status' => $definition->status->value,
            'inactive' => $definition->status !== TrainingDefinitionStatus::Active,
            'name' => $definition->name,
            'description' => $definition->description,
            'difficulty' => $definition->difficulty?->value,
            'difficultyLabel' => $definition->difficulty?->label(),
            'declaredMinutes' => $definition->declared_minutes,
            'mechanic' => $definition->kind === TrainingKind::Challenge ? $definition->mechanic : null,
            'mechanicLabel' => self::mechanicLabel($definition),
            'durationSeconds' => $definition->duration_seconds,
            'dartCount' => $definition->dart_count,
            'imageUrl' => $definition->imageUrl(),
            'favorite' => $favorite,
        ];
    }

    private static function mechanicLabel(TrainingDefinition $definition): ?string
    {
        if (! is_string($definition->mechanic) || $definition->mechanic === '') {
            return null;
        }

        if ($definition->kind !== TrainingKind::Challenge) {
            return null;
        }

        return TrainingChallengeCatalog::label($definition->mechanic);
    }

    /**
     * @return array<string, mixed>
     */
    public static function exercise(string $exerciseKey, bool $favorite): array
    {
        $limits = TrainingExerciseCatalog::parameters($exerciseKey);

        return [
            'id' => $exerciseKey,
            'kind' => TrainingKind::Exercise->value,
            'status' => 'active',
            'inactive' => false,
            'name' => TrainingExerciseCatalog::label($exerciseKey),
            'description' => null,
            'difficulty' => null,
            'difficultyLabel' => null,
            'declaredMinutes' => null,
            'exerciseKey' => $exerciseKey,
            'durationSeconds' => $limits['durationSeconds'] ?? null,
            'dartCount' => $limits['dartCount'] ?? null,
            'imageUrl' => null,
            'favorite' => $favorite,
        ];
    }
}
