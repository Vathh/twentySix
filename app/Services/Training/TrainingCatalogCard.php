<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingChallengeCatalog;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingExerciseVariant;
use App\Domain\Training\TrainingItemKind;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Models\Training\TrainingDefinitionItem;

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
            'challengeKey' => $definition->kind === TrainingKind::Challenge ? $definition->mechanic : null,
            'badgeThresholds' => $definition->kind === TrainingKind::Challenge ? $definition->badge_thresholds : null,
            'startsAt' => $definition->kind === TrainingKind::Event ? $definition->starts_at?->toIso8601String() : null,
            'endsAt' => $definition->kind === TrainingKind::Event ? $definition->ends_at?->toIso8601String() : null,
            'attemptsPerWindow' => $definition->kind === TrainingKind::Event ? $definition->attempts_per_window : null,
            'windowMode' => $definition->kind === TrainingKind::Event ? $definition->window_mode : null,
            'durationSeconds' => $definition->duration_seconds,
            'dartCount' => $definition->dart_count,
            'imageUrl' => $definition->imageUrl(),
            'favorite' => $favorite,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(TrainingDefinition $definition, bool $favorite, int $completionCount): array
    {
        $card = self::from($definition, $favorite);
        $card['completionCount'] = $completionCount;
        $card['items'] = $definition->relationLoaded('items')
            ? $definition->items
                ->sortBy('position')
                ->values()
                ->map(fn (TrainingDefinitionItem $item): array => self::item($item))
                ->all()
            : [];

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(TrainingDefinitionItem $item): array
    {
        $isBreak = $item->item_kind === TrainingItemKind::Break;

        return [
            'position' => (int) $item->position,
            'kind' => $item->item_kind->value,
            'exerciseKey' => $isBreak ? null : $item->exercise_key,
            'name' => $isBreak ? 'Przerwa' : $item->exerciseLabel(),
            'description' => null,
            'durationSeconds' => $item->duration_seconds,
            'dartCount' => $item->dart_count,
            'breakSeconds' => $item->break_seconds,
            'scored' => $isBreak ? false : (bool) $item->scored,
            'variant' => $item->variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exercise(string $exerciseKey, bool $favorite, ?string $variant = null): array
    {
        $limits = TrainingExerciseCatalog::parameters($exerciseKey);
        $normalized = TrainingExerciseVariant::normalize($variant);

        return [
            'id' => $exerciseKey,
            'kind' => TrainingKind::Exercise->value,
            'status' => 'active',
            'inactive' => false,
            'name' => TrainingExerciseVariant::displayName($exerciseKey, $normalized),
            'description' => null,
            'difficulty' => null,
            'difficultyLabel' => null,
            'declaredMinutes' => null,
            'exerciseKey' => $exerciseKey,
            'variant' => $normalized === '' ? null : $normalized,
            'durationSeconds' => $limits['durationSeconds'] ?? null,
            'dartCount' => $limits['dartCount'] ?? null,
            'imageUrl' => null,
            'favorite' => $favorite,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function challenge(TrainingDefinition $definition, bool $favorite, int $completionCount = 0): array
    {
        $card = self::detail($definition, $favorite, $completionCount);
        $key = is_string($definition->mechanic) ? $definition->mechanic : null;
        if ($key && TrainingChallengeCatalog::exists($key)) {
            $card['name'] = TrainingChallengeCatalog::name($key);
            if ($card['description'] === null || $card['description'] === '') {
                $card['description'] = TrainingChallengeCatalog::description($key);
            }
        }

        return $card;
    }
}
