<?php

namespace App\Repositories\PointScheme;

use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Enums\PointSchemeFormat;
use App\Models\PointScheme\PointSchemeRule;

class PointSchemeRuleRepository
{
    public function find(int $schemeId, PointSchemeFormat $format, int $place): ?PointSchemeRuleDomain
    {
        $rule = PointSchemeRule::query()
            ->where('point_scheme_id', $schemeId)
            ->where('format', $format->value)
            ->where('place_from', '<=', $place)
            ->where('place_to', '>=', $place)
            ->orderByDesc('place_from')
            ->first();

        return $rule !== null ? PointSchemeRuleDomain::fromEloquent($rule) : null;
    }
}
