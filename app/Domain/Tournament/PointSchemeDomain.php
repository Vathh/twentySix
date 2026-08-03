<?php

namespace App\Domain\Tournament;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Enums\GameStage;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use Illuminate\Support\Collection;

class PointSchemeDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['rules'];

    /**
     * @param int $id
     * @param string $name
     * @param int $minPlayers
     * @param int $maxPlayers
     * @param Collection<PointSchemeRuleDomain> $rules
     */
    public function __construct(
        public readonly int         $id,
        public readonly string      $name,
        public readonly int         $minPlayers,
        public readonly int         $maxPlayers,
        public readonly Collection  $rules,
    )
    {
    }

    /**
     * @param PointScheme $scheme
     * @param array $with
     * @return self
     */
    public static function fromEloquent(PointScheme $scheme, array $with = []): self
    {
        self::assertRelationsLoaded($scheme, $with, self::RELATIONS);

        return new self(
            id: $scheme->id,
            name: $scheme->name,
            minPlayers: $scheme->min_players,
            maxPlayers: $scheme->max_players,
            rules: in_array('rules', $with)
                ? $scheme->rules->map(fn(PointSchemeRule $rule) => PointSchemeRuleDomain::fromEloquent($rule))
                : collect()
        );
    }

    public function getPointsAmount(GameStage $stage, ?int $place): int
    {
        return $this->rules->where(fn(PointSchemeRuleDomain $rule) =>
                                        $rule->stage === $stage && $rule->place === $place)
                            ->first()->points;
    }
}

