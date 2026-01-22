<?php

namespace App\Repositories;

use App\Domain\Tournament\PointSchemeDomain;
use App\Models\PointScheme;
use App\Models\Tournament;
use Illuminate\Support\Collection;

class PointSchemeRepository
{
    /**
     * @return Collection<PointSchemeDomain>
     */
    public function findAll(): Collection
    {
        return PointScheme::all()->map(fn($scheme) => PointSchemeDomain::fromEloquent($scheme));
    }
}
