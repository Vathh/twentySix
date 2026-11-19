<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingFavoriteRepository;
use App\Support\Http\DomainExceptionHttp;

class TrainingCatalogService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingFavoriteRepository $favorites,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $user, string $slug): array
    {
        $kind = $this->kind($slug);
        if ($kind === TrainingKind::Exercise) {
            $favoriteKeys = array_fill_keys($this->favorites->keysFor((int) $user->id, $kind), true);
            $items = [];
            foreach (array_keys(TrainingExerciseCatalog::all()) as $exerciseKey) {
                $items[] = TrainingCatalogCard::exercise($exerciseKey, isset($favoriteKeys[$exerciseKey]));
            }

            return $items;
        }

        $favoriteIds = array_fill_keys($this->favorites->idsFor((int) $user->id, $kind), true);

        return $this->definitions->activeOfKind($kind)
            ->map(fn ($definition) => TrainingCatalogCard::from($definition, isset($favoriteIds[$definition->id])))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, int $id): array
    {
        $definition = $this->definitions->find($id);
        if ($definition->kind === TrainingKind::Event
            || $definition->status === TrainingDefinitionStatus::Cancelled
            || $definition->status === TrainingDefinitionStatus::Removed) {
            $this->missing();
        }

        $favorite = $this->favorites->exists((int) $user->id, (int) $definition->id);
        if ($definition->status !== TrainingDefinitionStatus::Active && ! $favorite) {
            $this->missing();
        }

        return TrainingCatalogCard::from($definition, $favorite);
    }

    /**
     * @return array<string, mixed>
     */
    public function showExercise(User $user, string $exerciseKey): array
    {
        if (TrainingExerciseCatalog::parameters($exerciseKey) === null) {
            $this->missingExercise();
        }

        $favorite = $this->favorites->exerciseExists((int) $user->id, $exerciseKey);

        return TrainingCatalogCard::exercise($exerciseKey, $favorite);
    }

    private function kind(string $slug): TrainingKind
    {
        $kind = TrainingKind::tryFromSlug($slug);
        if ($kind === null || $kind === TrainingKind::Event) {
            throw new TrainingException(
                'Nie ma takiego katalogu.',
                'training_catalog_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return $kind;
    }

    private function missingExercise(): never
    {
        throw new TrainingException(
            'Wskazane ćwiczenie nie istnieje.',
            'training_exercise_not_found',
            DomainExceptionHttp::NOT_FOUND,
        );
    }

    private function missing(): never
    {
        throw new TrainingException(
            'Nie ma takiej treści.',
            'training_definition_not_found',
            DomainExceptionHttp::NOT_FOUND,
        );
    }
}
