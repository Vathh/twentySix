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
        if (PointScheme::query()->exists()) {
            return;
        }

        if (! Schema::hasColumn('point_scheme_rules', 'place_from')) {
            return;
        }

        $this->seedBands();
    }

    public function replaceAll(): void
    {
        PointSchemeRule::query()->delete();
        PointScheme::query()->delete();
        $this->seedBands();
    }

    private function seedBands(): void
    {
        $now = now();

        foreach (SeasonPointTable::definitions() as $band) {
            $scheme = PointScheme::create([
                'name' => $band['name'],
                'min_players' => $band['min_players'],
                'max_players' => $band['max_players'],
            ]);

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
