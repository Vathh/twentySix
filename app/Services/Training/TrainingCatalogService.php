<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingChallengeCatalog;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingExerciseCatalog;
use App\Domain\Training\TrainingKind;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingFavoriteRepository;
use App\Repositories\Training\TrainingSessionRepository;
use App\Support\Http\DomainExceptionHttp;

class TrainingCatalogService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingFavoriteRepository $favorites,
        private TrainingSessionRepository $sessions,
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

        if ($kind === TrainingKind::Challenge) {
            $favoriteIds = array_fill_keys($this->favorites->idsFor((int) $user->id, $kind), true);
            $items = [];
            foreach ($this->definitions->catalogChallenges() as $definition) {
                if ($definition->status !== TrainingDefinitionStatus::Active) {
                    continue;
                }
                $items[] = TrainingCatalogCard::from($definition, isset($favoriteIds[$definition->id]));
            }

            return $items;
        }

        if ($kind === TrainingKind::Event) {
            return $this->definitions->visible(TrainingKind::Event)
                ->filter(fn ($definition) => in_array($definition->status, [
                    TrainingDefinitionStatus::Active,
                    TrainingDefinitionStatus::Paused,
                    TrainingDefinitionStatus::Closing,
                    TrainingDefinitionStatus::Finished,
                ], true))
                ->filter(function ($definition) {
                    if ($definition->status === TrainingDefinitionStatus::Finished) {
                        return true;
                    }
                    // Aktywne/paused/closing tylko gdy już wystartowały albo są w oknie widoczności.
                    return $definition->status !== TrainingDefinitionStatus::Inactive;
                })
                ->map(fn ($definition) => TrainingCatalogCard::from($definition, false))
                ->values()
                ->all();
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
        $definition = $this->definitions->findWithItems($id);
        if ($definition->status === TrainingDefinitionStatus::Cancelled
            || $definition->status === TrainingDefinitionStatus::Removed) {
            $this->missing();
        }

        $favorite = $definition->kind === TrainingKind::Event
            ? false
            : $this->favorites->exists((int) $user->id, (int) $definition->id);

        if ($definition->kind === TrainingKind::Event) {
            if (! in_array($definition->status, [
                TrainingDefinitionStatus::Active,
                TrainingDefinitionStatus::Paused,
                TrainingDefinitionStatus::Closing,
                TrainingDefinitionStatus::Finished,
            ], true)) {
                $this->missing();
            }
        } elseif ($definition->status !== TrainingDefinitionStatus::Active && ! $favorite) {
            $this->missing();
        }

        $completionCount = $this->sessions->countCompletedFor((int) $user->id, (int) $definition->id);

        if ($definition->kind === TrainingKind::Challenge) {
            return TrainingCatalogCard::challenge($definition, $favorite, $completionCount);
        }

        return TrainingCatalogCard::detail($definition, $favorite, $completionCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function showChallenge(User $user, string $challengeKey): array
    {
        if (! TrainingChallengeCatalog::exists($challengeKey)) {
            throw new TrainingException(
                'Nie ma takiego wyzwania.',
                'training_challenge_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        $definition = $this->definitions->requireCatalogChallenge($challengeKey);
        if ($definition->status === TrainingDefinitionStatus::Cancelled
            || $definition->status === TrainingDefinitionStatus::Removed) {
            $this->missing();
        }

        $favorite = $this->favorites->exists((int) $user->id, (int) $definition->id);
        if ($definition->status !== TrainingDefinitionStatus::Active && ! $favorite) {
            $this->missing();
        }

        $completionCount = $this->sessions->countCompletedFor((int) $user->id, (int) $definition->id);

        return TrainingCatalogCard::challenge($definition, $favorite, $completionCount);
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
        if ($kind === null) {
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
