<?php

namespace App\Domain\Tournament;

use App\Enums\GameStage;
use App\Models\PointSchemeRule;

class PointSchemeRuleDomain
{

    public function __construct(
        public readonly int $id,
        public readonly GameStage $stage,
        public readonly ?int $place,
        public readonly int $points,
    )
    {
    }

    /**
     * @param PointSchemeRule $rule
     * @return self
     */
    public static function fromEloquent(PointSchemeRule $rule): self
    {
        return new self(
            id: $rule->id,
            stage: $rule->elimination_stage,
            place: $rule->place ?: null,
            points: $rule->points
        );
    }
}
