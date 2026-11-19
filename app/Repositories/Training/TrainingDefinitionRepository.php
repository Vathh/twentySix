<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingDefinitionDraft;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Support\Http\DomainExceptionHttp;
use Illuminate\Support\Collection;

class TrainingDefinitionRepository
{
    public function store(TrainingDefinitionDraft $draft): TrainingDefinition
    {
        $definition = TrainingDefinition::query()->create([
            'kind' => $draft->kind,
            'status' => TrainingDefinitionStatus::Inactive,
            'name' => $draft->name,
            'description' => $draft->description,
            'image_path' => null,
            'difficulty' => $draft->difficulty,
            'declared_minutes' => $draft->declaredMinutes,
            'mechanic' => $draft->mechanic,
            'duration_seconds' => $draft->durationSeconds,
            'dart_count' => $draft->dartCount,
            'published_at' => null,
        ]);

        foreach ($draft->items as $index => $item) {
            $definition->items()->create([
                'position' => $index + 1,
                'item_kind' => $item->kind,
                'exercise_key' => $item->exerciseKey,
                'duration_seconds' => $item->durationSeconds,
                'dart_count' => $item->dartCount,
                'break_seconds' => $item->breakSeconds,
            ]);
        }

        return $definition->load('items');
    }

    /**
     * Wołać wewnątrz transakcji.
     */
    public function lock(int $id): TrainingDefinition
    {
        $definition = TrainingDefinition::query()->lockForUpdate()->find($id);
        if (! $definition instanceof TrainingDefinition) {
            throw new TrainingException(
                'Nie ma takiej definicji treningu.',
                'training_definition_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return $definition;
    }

    public function markActive(TrainingDefinition $definition): TrainingDefinition
    {
        $definition->status = TrainingDefinitionStatus::Active;
        if ($definition->published_at === null) {
            $definition->published_at = now();
        }
        $definition->save();

        return $definition->load('items');
    }

    public function find(int $id): TrainingDefinition
    {
        $definition = TrainingDefinition::query()->find($id);
        if (! $definition instanceof TrainingDefinition) {
            throw new TrainingException(
                'Nie ma takiej definicji treningu.',
                'training_definition_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return $definition;
    }

    public function findWithItems(int $id): TrainingDefinition
    {
        return $this->find($id)->load('items');
    }

    /**
     * @return Collection<int, TrainingDefinition>
     */
    public function visible(TrainingKind $kind): Collection
    {
        return TrainingDefinition::query()
            ->where('kind', $kind)
            ->whereIn('status', [
                TrainingDefinitionStatus::Inactive,
                TrainingDefinitionStatus::Active,
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TrainingDefinition>
     */
    public function activeOfKind(TrainingKind $kind): Collection
    {
        return TrainingDefinition::query()
            ->where('kind', $kind)
            ->where('status', TrainingDefinitionStatus::Active)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array{active: int, inactive: int}>
     */
    public function visibleCounts(): array
    {
        $counts = [];
        foreach (TrainingKind::cases() as $kind) {
            $counts[$kind->value] = [
                'active' => $this->count($kind, TrainingDefinitionStatus::Active),
                'inactive' => $this->count($kind, TrainingDefinitionStatus::Inactive),
            ];
        }

        return $counts;
    }

    public function replace(TrainingDefinition $definition, TrainingDefinitionDraft $draft): TrainingDefinition
    {
        $definition->name = $draft->name;
        $definition->description = $draft->description;
        $definition->difficulty = $draft->difficulty;
        $definition->declared_minutes = $draft->declaredMinutes;
        $definition->mechanic = $draft->mechanic;
        $definition->duration_seconds = $draft->durationSeconds;
        $definition->dart_count = $draft->dartCount;
        $definition->save();

        $definition->items()->delete();
        foreach ($draft->items as $index => $item) {
            $definition->items()->create([
                'position' => $index + 1,
                'item_kind' => $item->kind,
                'exercise_key' => $item->exerciseKey,
                'duration_seconds' => $item->durationSeconds,
                'dart_count' => $item->dartCount,
                'break_seconds' => $item->breakSeconds,
            ]);
        }

        return $definition->load('items');
    }

    public function setImagePath(TrainingDefinition $definition, ?string $path): TrainingDefinition
    {
        $definition->image_path = $path;
        $definition->save();

        return $definition;
    }

    public function markStatus(TrainingDefinition $definition, TrainingDefinitionStatus $status): TrainingDefinition
    {
        $definition->status = $status;
        $definition->save();

        return $definition;
    }

    private function count(TrainingKind $kind, TrainingDefinitionStatus $status): int
    {
        return TrainingDefinition::query()
            ->where('kind', $kind)
            ->where('status', $status)
            ->count();
    }
}
