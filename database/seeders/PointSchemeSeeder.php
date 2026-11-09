<?php

namespace Database\Seeders;

use App\Domain\Tournament\SeasonPointTable;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Pasma 4–128 z SeasonPointTable (docs/design_point_schemes.md).
 */
class PointSchemeSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('point_scheme_rules', 'place_from')) {
            return;
        }

        $existing = PointScheme::query();
        if (Schema::hasColumn('point_schemes', 'organization_id')) {
            $existing->whereNull('organization_id');
        }

        if ($existing->exists()) {
            return;
        }

        $this->seedBands();
    }

    public function replaceAll(): void
    {
        if (Schema::hasColumn('point_schemes', 'organization_id')) {
            $systemIds = PointScheme::query()->whereNull('organization_id')->pluck('id');
            PointSchemeRule::query()->whereIn('point_scheme_id', $systemIds)->delete();
            PointScheme::query()->whereIn('id', $systemIds)->delete();
        } else {
            PointSchemeRule::query()->delete();
            PointScheme::query()->delete();
        }

        $this->seedBands();
    }

    private function seedBands(): void
    {
        $now = now();

        foreach (SeasonPointTable::definitions() as $band) {
            $attributes = [
                'name' => $band['name'],
                'min_players' => $band['min_players'],
                'max_players' => $band['max_players'],
            ];
            if (Schema::hasColumn('point_schemes', 'organization_id')) {
                $attributes['organization_id'] = null;
                $attributes['is_active'] = true;
            }

            $scheme = PointScheme::create($attributes);

            $rows = [];
            foreach ($band['rules'] as $rule) {
                $rows[] = [
                    'point_scheme_id' => $scheme->id,
                    'format' => $rule['format']->value,
                    'place_from' => $rule['place_from'],
                    'place_to' => $rule['place_to'],
                    'points' => $rule['points'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            PointSchemeRule::insert($rows);
        }
    }
}
