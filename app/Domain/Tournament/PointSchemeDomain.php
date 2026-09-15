<?php

namespace App\Domain\Tournament;

use App\Domain\Concerns\AssertsRelationsLoaded;
use App\Enums\TournamentFormat;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use Illuminate\Support\Collection;

class PointSchemeDomain
{
    use AssertsRelationsLoaded;

    /** @var list<string> */
    private const RELATIONS = ['rules'];

    /**
     * @param  Collection<int, PointSchemeRuleDomain>  $rules
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $minPlayers,
        public readonly int $maxPlayers,
        public readonly Collection $rules,
    ) {}

    public static function fromEloquent(PointScheme $scheme, array $with = []): self
    {
        self::assertRelationsLoaded($scheme, $with, self::RELATIONS);

        return new self(
            id: $scheme->id,
            name: $scheme->name,
            minPlayers: $scheme->min_players,
            maxPlayers: $scheme->max_players,
            rules: in_array('rules', $with, true)
                ? $scheme->rules->map(fn (PointSchemeRule $rule) => PointSchemeRuleDomain::fromEloquent($rule))
                : collect()
        );
    }

    public function pointsForPlace(TournamentFormat $tournamentFormat, int $place): ?int
    {
        $format = $tournamentFormat->seasonPointFormat();

        $rule = $this->rules
            ->filter(fn (PointSchemeRuleDomain $rule) => $rule->matches($format, $place))
            ->sortByDesc(fn (PointSchemeRuleDomain $rule) => $rule->placeFrom)
            ->first();

        return $rule?->points;
    }
}
