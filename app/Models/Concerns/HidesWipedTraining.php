<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HidesWipedTraining
{
    protected static function bootHidesWipedTraining(): void
    {
        static::addGlobalScope('without_wiped_training', function (Builder $builder): void {
            $builder->whereNull($builder->getModel()->getTable().'.wipe_id');
        });
    }
}
