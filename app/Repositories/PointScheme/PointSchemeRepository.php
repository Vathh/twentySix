<?php

namespace App\Repositories\PointScheme;

use App\Domain\Tournament\PointSchemeDomain;
use App\Enums\PointSchemeFormat;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use Illuminate\Support\Collection;

class PointSchemeRepository
{
    /**
     * Aktywne schematy systemowe (wspólne, organization_id null).
     *
     * @return Collection<int, PointSchemeDomain>
     */
    public function findSystem(): Collection
    {
        return PointScheme::query()
            ->whereNull('organization_id')
            ->where('is_active', true)
            ->orderBy('min_players')
            ->get()
            ->map(fn (PointScheme $scheme) => PointSchemeDomain::fromEloquent($scheme));
    }

    /**
     * @return Collection<int, PointSchemeDomain>
     */
    public function findActiveForOrganization(int $organizationId, bool $withRules = false): Collection
    {
        $query = PointScheme::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('min_players');

        if ($withRules) {
            $query->with('rules');
        }

        return $query->get()->map(fn (PointScheme $scheme) => PointSchemeDomain::fromEloquent(
            $scheme,
            $withRules ? ['rules'] : [],
        ));
    }

    /**
     * @param  list<int>  $ids
     */
    public function deactivate(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        PointScheme::query()->whereIn('id', $ids)->update(['is_active' => false]);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteWithRules(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        PointSchemeRule::query()->whereIn('point_scheme_id', $ids)->delete();
        PointScheme::query()->whereIn('id', $ids)->delete();
    }

    /**
     * @param  list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>  $rules
     */
    public function replaceRules(int $schemeId, array $rules): void
    {
        PointSchemeRule::query()->where('point_scheme_id', $schemeId)->delete();
        $this->insertRules($schemeId, $rules);
    }

    /**
     * @param  list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>  $rules
     */
    public function createForOrganization(int $organizationId, string $name, int $minPlayers, int $maxPlayers, array $rules): int
    {
        $scheme = PointScheme::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'min_players' => $minPlayers,
            'max_players' => $maxPlayers,
            'is_active' => true,
        ]);

        $this->insertRules($scheme->id, $rules);

        return $scheme->id;
    }

    /**
     * @param  list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>  $rules
     */
    private function insertRules(int $schemeId, array $rules): void
    {
        if ($rules === []) {
            return;
        }

        $now = now();
        PointSchemeRule::query()->insert(array_map(fn (array $rule) => [
            'point_scheme_id' => $schemeId,
            'format' => $rule['format']->value,
            'place_from' => $rule['place_from'],
            'place_to' => $rule['place_to'],
            'points' => $rule['points'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rules));
    }
}
