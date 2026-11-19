<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingFavoriteRules;
use App\Domain\Training\TrainingKind;
use App\Support\Http\DomainExceptionHttp;
use App\Models\Training\TrainingDefinition;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingFavoriteRepository;
use Illuminate\Support\Facades\DB;

class TrainingFavoriteService
{
    public function __construct(
        private TrainingFavoriteRepository $favorites,
        private TrainingDefinitionRepository $definitions,
    ) {}

    /**
     * @return array{challenges: list<array<string, mixed>>, plans: list<array<string, mixed>>, exercises: list<array<string, mixed>>}
     */
    public function grouped(User $user): array
    {
        $grouped = [
            'challenges' => [],
            'plans' => [],
            'exercises' => [],
        ];

        foreach ($this->favorites->forUser((int) $user->id) as $row) {
            if (is_string($row->exercise_key) && $row->exercise_key !== '') {
                if ($row->kind !== TrainingKind::Exercise || TrainingExerciseCatalog::parameters($row->exercise_key) === null) {
                    continue;
                }
                $grouped['exercises'][] = TrainingCatalogCard::exercise($row->exercise_key, true);

                continue;
            }

            $definition = $row->definition;
            if (! $definition instanceof TrainingDefinition
                || $definition->status === TrainingDefinitionStatus::Cancelled
                || $definition->status === TrainingDefinitionStatus::Removed) {
                continue;
            }

            $slug = $row->kind->slug();
            if (! array_key_exists($slug, $grouped)) {
                continue;
            }

            $grouped[$slug][] = TrainingCatalogCard::from($definition, true);
        }

        return $grouped;
    }

    /**
     * @return array{created: bool, item: array<string, mixed>}
     */
    public function add(User $user, int $definitionId): array
    {
        return DB::transaction(function () use ($user, $definitionId): array {
            $definition = $this->definitions->lock($definitionId);
            $already = $this->favorites->exists((int) $user->id, (int) $definition->id);
            if (! $already) {
                TrainingFavoriteRules::assertFavoritable($definition->kind, $definition->status);
                $this->favorites->add((int) $user->id, (int) $definition->id, $definition->kind);
            }

            return [
                'created' => ! $already,
                'item' => TrainingCatalogCard::from($definition, true),
            ];
        });
    }

    /**
     * @return array{removed: bool}
     */
    public function remove(User $user, int $definitionId): array
    {
        $removed = DB::transaction(
            fn () => $this->favorites->remove((int) $user->id, $definitionId),
        );

        return ['removed' => $removed];
    }

    /**
     * @return array{created: bool, item: array<string, mixed>}
     */
    public function addExercise(User $user, string $exerciseKey): array
    {
        if (TrainingExerciseCatalog::parameters($exerciseKey) === null) {
            throw new TrainingException(
                'Wskazane ćwiczenie nie istnieje.',
                'training_exercise_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return DB::transaction(function () use ($user, $exerciseKey): array {
            $already = $this->favorites->exerciseExists((int) $user->id, $exerciseKey);
            if (! $already) {
                $this->favorites->addExercise((int) $user->id, $exerciseKey);
            }

            return [
                'created' => ! $already,
                'item' => TrainingCatalogCard::exercise($exerciseKey, true),
            ];
        });
    }

    /**
     * @return array{removed: bool}
     */
    public function removeExercise(User $user, string $exerciseKey): array
    {
        $removed = DB::transaction(
            fn () => $this->favorites->removeExercise((int) $user->id, $exerciseKey),
        );

        return ['removed' => $removed];
    }

    /**
     * @param  list<int>  $definitionIds
     * @return list<array<string, mixed>>
     */
    public function reorder(User $user, string $slug, array $definitionIds): array
    {
        $kind = TrainingKind::tryFromSlug($slug);
        if ($kind === null || $kind === TrainingKind::Event) {
            throw new TrainingException(
                'Nie ma takiej zakładki.',
                'training_favorite_order',
            );
        }

        DB::transaction(function () use ($user, $kind, $definitionIds): void {
            $this->favorites->replaceOrder((int) $user->id, $kind, $definitionIds);
        });

        return $this->grouped($user)[$kind->slug()];
    }

    /**
     * @param  list<string>  $exerciseKeys
     * @return list<array<string, mixed>>
     */
    public function reorderExercises(User $user, array $exerciseKeys): array
    {
        DB::transaction(function () use ($user, $exerciseKeys): void {
            $this->favorites->replaceExerciseOrder((int) $user->id, $exerciseKeys);
        });

        return $this->grouped($user)['exercises'];
    }
}
