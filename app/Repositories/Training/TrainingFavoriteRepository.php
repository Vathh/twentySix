<?php

namespace App\Repositories\Training;

use App\Domain\Training\TrainingException;
use App\Domain\Training\TrainingKind;
use App\Models\Training\TrainingFavorite;
use Illuminate\Support\Collection;

class TrainingFavoriteRepository
{
    /**
     * @return Collection<int, TrainingFavorite>
     */
    public function forUser(int $userId): Collection
    {
        return TrainingFavorite::query()
            ->with('definition')
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function exists(int $userId, int $definitionId): bool
    {
        return TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function idsFor(int $userId, TrainingKind $kind): array
    {
        return TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->orderBy('position')
            ->pluck('definition_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function keysFor(int $userId, TrainingKind $kind): array
    {
        return TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->orderBy('position')
            ->pluck('exercise_key')
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();
    }

    public function exerciseExists(int $userId, string $exerciseKey): bool
    {
        return TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('exercise_key', $exerciseKey)
            ->exists();
    }

    public function add(int $userId, int $definitionId, TrainingKind $kind): bool
    {
        $existing = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->lockForUpdate()
            ->first();
        if ($existing instanceof TrainingFavorite) {
            return false;
        }

        TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->lockForUpdate()
            ->get();

        $max = (int) TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->max('position');

        TrainingFavorite::query()->create([
            'user_id' => $userId,
            'definition_id' => $definitionId,
            'kind' => $kind,
            'position' => $max + 1,
        ]);

        return true;
    }

    public function addExercise(int $userId, string $exerciseKey): bool
    {
        $existing = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('exercise_key', $exerciseKey)
            ->lockForUpdate()
            ->first();
        if ($existing instanceof TrainingFavorite) {
            return false;
        }

        TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', TrainingKind::Exercise)
            ->lockForUpdate()
            ->get();

        $max = (int) TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', TrainingKind::Exercise)
            ->max('position');

        TrainingFavorite::query()->create([
            'user_id' => $userId,
            'definition_id' => null,
            'exercise_key' => $exerciseKey,
            'kind' => TrainingKind::Exercise,
            'position' => $max + 1,
        ]);

        return true;
    }

    public function remove(int $userId, int $definitionId): bool
    {
        $row = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('definition_id', $definitionId)
            ->lockForUpdate()
            ->first();
        if (! $row instanceof TrainingFavorite) {
            return false;
        }

        $kind = $row->kind;
        $row->delete();
        $this->compact($userId, $kind);

        return true;
    }

    public function removeExercise(int $userId, string $exerciseKey): bool
    {
        $row = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('exercise_key', $exerciseKey)
            ->lockForUpdate()
            ->first();
        if (! $row instanceof TrainingFavorite) {
            return false;
        }

        $kind = $row->kind;
        $row->delete();
        $this->compact($userId, $kind);

        return true;
    }

    /**
     * @param  list<int>  $definitionIds
     */
    public function replaceOrder(int $userId, TrainingKind $kind, array $definitionIds): void
    {
        $rows = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        $current = $rows->map(fn (TrainingFavorite $row) => (int) $row->definition_id)->all();
        $wanted = array_map(static fn ($id) => (int) $id, $definitionIds);
        $currentSorted = $current;
        $wantedSorted = $wanted;
        sort($currentSorted);
        sort($wantedSorted);

        if ($currentSorted !== $wantedSorted) {
            throw new TrainingException(
                'Kolejność dotyczy tylko pozycji na tej zakładce.',
                'training_favorite_order',
            );
        }

        $byDefinition = $rows->keyBy(fn (TrainingFavorite $row) => (int) $row->definition_id);
        foreach ($rows as $offset => $row) {
            $row->position = 1000 + $offset;
            $row->save();
        }
        foreach ($wanted as $index => $definitionId) {
            $row = $byDefinition->get($definitionId);
            if (! $row instanceof TrainingFavorite) {
                throw new TrainingException(
                    'Kolejność dotyczy tylko pozycji na tej zakładce.',
                    'training_favorite_order',
                );
            }
            $row->position = $index + 1;
            $row->save();
        }
    }

    /**
     * @param  list<string>  $exerciseKeys
     */
    public function replaceExerciseOrder(int $userId, array $exerciseKeys): void
    {
        $rows = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', TrainingKind::Exercise)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        $current = $rows->map(fn (TrainingFavorite $row) => (string) $row->exercise_key)->all();
        $wanted = array_map(static fn ($key) => (string) $key, $exerciseKeys);
        $currentSorted = $current;
        $wantedSorted = $wanted;
        sort($currentSorted);
        sort($wantedSorted);

        if ($currentSorted !== $wantedSorted) {
            throw new TrainingException(
                'Kolejność dotyczy tylko pozycji na tej zakładce.',
                'training_favorite_order',
            );
        }

        $byKey = $rows->keyBy(fn (TrainingFavorite $row) => (string) $row->exercise_key);
        foreach ($rows as $offset => $row) {
            $row->position = 1000 + $offset;
            $row->save();
        }
        foreach ($wanted as $index => $exerciseKey) {
            $row = $byKey->get($exerciseKey);
            if (! $row instanceof TrainingFavorite) {
                throw new TrainingException(
                    'Kolejność dotyczy tylko pozycji na tej zakładce.',
                    'training_favorite_order',
                );
            }
            $row->position = $index + 1;
            $row->save();
        }
    }

    public function deleteForDefinition(int $definitionId): void
    {
        TrainingFavorite::query()->where('definition_id', $definitionId)->delete();
    }

    private function compact(int $userId, TrainingKind $kind): void
    {
        $rows = TrainingFavorite::query()
            ->where('user_id', $userId)
            ->where('kind', $kind)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $offset => $row) {
            $row->position = 1000 + $offset;
            $row->save();
        }
        foreach ($rows as $index => $row) {
            $row->position = $index + 1;
            $row->save();
        }
    }
}
