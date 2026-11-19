<?php

namespace App\Services\Training;

use App\Domain\Training\TrainingDefinitionDraft;
use App\Domain\Training\TrainingDefinitionRules;
use App\Domain\Training\TrainingDefinitionStatus;
use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingItemKind;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingDefinition;
use App\Models\Users\User;
use App\Repositories\Training\TrainingDefinitionRepository;
use App\Repositories\Training\TrainingFavoriteRepository;
use App\Support\Http\DomainExceptionHttp;
use App\Support\Media\SquareJpeg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrainingDefinitionService
{
    public function __construct(
        private TrainingDefinitionRepository $definitions,
        private TrainingFavoriteRepository $favorites,
    ) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function create(User $actor, array $payload, ?UploadedFile $image = null): TrainingDefinition
    {
        $this->assertAdmin($actor);
        $binary = $this->encodedImage($image);
        $draft = TrainingDefinitionDraft::fromPayload($payload);

        return DB::transaction(function () use ($draft, $binary): TrainingDefinition {
            $this->assertReferencedExercises($draft);
            $definition = $this->definitions->store($draft);
            if ($binary !== null) {
                $this->writeImage($definition, $binary);
            }

            return $definition->load('items');
        });
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function updateDraft(User $actor, int $id, array $payload, ?UploadedFile $image = null, bool $removeImage = false): TrainingDefinition
    {
        $this->assertAdmin($actor);
        $binary = $this->encodedImage($image);

        return DB::transaction(function () use ($id, $payload, $binary, $removeImage): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            TrainingDefinitionRules::assertDraftEditable($definition->status, $definition->isPublished());
            if (($payload['kind'] ?? null) !== $definition->kind->value) {
                throw new TrainingException(
                    'Rodzaju treści nie zmieniamy.',
                    'training_definition_invalid',
                );
            }

            $draft = TrainingDefinitionDraft::fromPayload($payload);
            $this->assertReferencedExercises($draft);
            $updated = $this->definitions->replace($definition, $draft);
            if ($binary !== null) {
                $this->writeImage($updated, $binary);
            } elseif ($removeImage) {
                $this->clearImage($updated);
            }

            return $updated->load('items');
        });
    }

    public function activate(User $actor, int $id): TrainingDefinition
    {
        $this->assertAdmin($actor);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            $definition->load('items');
            TrainingDefinitionRules::assertCanActivate($definition->status);
            $this->assertStoredExercisesKnown($definition);

            return $this->definitions->markActive($definition);
        });
    }

    public function deactivate(User $actor, int $id): TrainingDefinition
    {
        $this->assertAdmin($actor);

        return DB::transaction(function () use ($id): TrainingDefinition {
            $definition = $this->definitions->lock($id);
            TrainingDefinitionRules::assertCanDeactivate($definition->status);

            return $this->definitions->markStatus($definition, TrainingDefinitionStatus::Inactive)->load('items');
        });
    }

    public function cancel(User $actor, int $id): void
    {
        $this->assertAdmin($actor);

        DB::transaction(function () use ($id): void {
            $definition = $this->definitions->lock($id);
            TrainingDefinitionRules::assertCanCancel($definition->kind, $definition->status);
            $this->definitions->markStatus($definition, TrainingDefinitionStatus::Cancelled);
            $this->favorites->deleteForDefinition((int) $definition->id);
        });
    }

    public function remove(User $actor, int $id): void
    {
        $this->assertAdmin($actor);

        DB::transaction(function () use ($id): void {
            $definition = $this->definitions->lock($id);
            TrainingDefinitionRules::assertCanRemove($definition->kind, $definition->status);

            $this->definitions->markStatus($definition, TrainingDefinitionStatus::Removed);
            $this->favorites->deleteForDefinition((int) $definition->id);
        });
    }

    public function present(User $actor, int $id): TrainingDefinition
    {
        $this->assertAdmin($actor);
        $definition = $this->definitions->findWithItems($id);
        if (! $definition->status->listedInAdmin()) {
            throw new TrainingException(
                'Nie ma takiej treści.',
                'training_definition_not_found',
                DomainExceptionHttp::NOT_FOUND,
            );
        }

        return $definition;
    }

    public function draft(User $actor, int $id): TrainingDefinition
    {
        $definition = $this->present($actor, $id);
        TrainingDefinitionRules::assertDraftEditable($definition->status, $definition->isPublished());

        return $definition;
    }

    /**
     * @return array{active: Collection<int, TrainingDefinition>, inactive: Collection<int, TrainingDefinition>}
     */
    public function catalog(User $actor, TrainingKind $kind): array
    {
        $this->assertAdmin($actor);
        $rows = $this->definitions->visible($kind);

        return [
            'active' => $rows->filter(
                fn (TrainingDefinition $definition): bool => $definition->status === TrainingDefinitionStatus::Active,
            )->values(),
            'inactive' => $rows->filter(
                fn (TrainingDefinition $definition): bool => $definition->status === TrainingDefinitionStatus::Inactive,
            )->values(),
        ];
    }

    /**
     * @return array<string, array{active: int, inactive: int}>
     */
    public function counts(User $actor): array
    {
        $this->assertAdmin($actor);

        return $this->definitions->visibleCounts();
    }

    private function assertStoredExercisesKnown(TrainingDefinition $definition): void
    {
        foreach ($definition->items as $item) {
            if ($item->item_kind !== TrainingItemKind::Exercise) {
                continue;
            }
            if (! is_string($item->exercise_key) || $item->exercise_key === '') {
                throw new TrainingException(
                    'Wskazane ćwiczenie nie istnieje.',
                    'training_exercise_not_found',
                );
            }

            TrainingDefinitionRules::assertKnownExercise($item->exercise_key);
        }
    }

    private function assertAdmin(User $actor): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        throw new TrainingException(
            'Definicje treningu zapisuje administrator.',
            'training_forbidden',
            DomainExceptionHttp::FORBIDDEN,
        );
    }

    private function assertReferencedExercises(TrainingDefinitionDraft $draft): void
    {
        foreach ($draft->items as $item) {
            if ($item->kind !== TrainingItemKind::Exercise || $item->exerciseKey === null) {
                continue;
            }

            TrainingDefinitionRules::assertKnownExercise($item->exerciseKey);
        }
    }

    private function encodedImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        SquareJpeg::assertImage($image, 'image');

        return SquareJpeg::encode($image, 'image');
    }

    private function writeImage(TrainingDefinition $definition, string $binary): void
    {
        $path = 'training/'.$definition->id.'.jpg';
        $previous = $definition->image_path;
        Storage::disk('public')->put($path, $binary);

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->definitions->setImagePath($definition, $path);
    }

    private function clearImage(TrainingDefinition $definition): void
    {
        if (is_string($definition->image_path) && $definition->image_path !== '') {
            Storage::disk('public')->delete($definition->image_path);
        }

        $this->definitions->setImagePath($definition, null);
    }
}
