<?php

namespace App\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Domain nie robi I/O: `fromEloquent()` oczekuje, że wymagane relacje są już
 * wczytane (eager-load w Repository/Service, np. `Model::with([...])`).
 * Ten trait tylko waliduje — nigdy nie ładuje danych.
 */
trait AssertsRelationsLoaded
{
    /**
     * @param  array<int, string>  $with  relacje zażądane przez wywołującego
     * @param  array<int, string>  $allowed  relacje obsługiwane przez tę klasę Domain
     */
    private static function assertRelationsLoaded(Model $model, array $with, array $allowed): void
    {
        foreach (array_intersect($with, $allowed) as $relation) {
            self::assertRelationLoaded($model, $relation);
        }
    }

    /**
     * Sprawdza pojedynczą relację, w tym zagnieżdżoną (`"season.league"`).
     */
    private static function assertRelationLoaded(Model $model, string $relation): void
    {
        $current = $model;

        foreach (explode('.', $relation) as $segment) {
            if ($current === null) {
                return;
            }

            if (! $current->relationLoaded($segment)) {
                throw new InvalidArgumentException(sprintf(
                    '%s::fromEloquent() wymaga wczytanej relacji "%s" — dociągnij ją w Repository/Service przed wywołaniem (np. %s::with([\'%s\'])).',
                    static::class,
                    $relation,
                    get_class($model),
                    $relation,
                ));
            }

            $current = $current->{$segment};
        }
    }
}
