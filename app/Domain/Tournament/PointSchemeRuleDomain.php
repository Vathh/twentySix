<?php

namespace App\Domain\Tournament;

use App\Enums\PointSchemeFormat;
use App\Models\PointScheme\PointSchemeRule;

class PointSchemeRuleDomain
{
    public function __construct(
        public readonly int $id,
        public readonly PointSchemeFormat $format,
        public readonly int $placeFrom,
        public readonly int $placeTo,
        public readonly int $points,
    ) {}

    public static function fromEloquent(PointSchemeRule $rule): self
    {
        return new self(
            id: $rule->id,
            format: $rule->format,
            placeFrom: $rule->place_from,
            placeTo: $rule->place_to,
            points: $rule->points,
        );
    }

    public function matches(PointSchemeFormat $format, int $place): bool
    {
        return $this->format === $format
            && $place >= $this->placeFrom
            && $place <= $this->placeTo;
    }
}
