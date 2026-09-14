<?php

namespace App\Repositories\PointScheme;

use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Enums\GameStage;
use App\Models\PointScheme\PointSchemeRule;

class PointSchemeRuleRepository
{
    public function find(int $schemeId, GameStage $stage, ?int $place): ?PointSchemeRuleDomain
    {
        $query = PointSchemeRule::query()
            ->where('point_scheme_id', $schemeId)
            ->where('elimination_stage', $stage->value);

        if ($place === null) {
            $query->whereNull('place');
        } else {
            $query->where('place', $place);
        }

        $rule = $query->first();

        return $rule !== null ? PointSchemeRuleDomain::fromEloquent($rule) : null;
    }
}
