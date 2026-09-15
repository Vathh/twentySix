<?php

namespace Tests\Concerns;

use App\Models\PointScheme\PointSchemeRule;

trait InsertsPointSchemeRules
{
    /**
     * @param  list<array{format?: string, place_from: int, place_to: int, points: int}>  $rows
     */
    protected function insertPointSchemeRules(int $schemeId, array $rows): void
    {
        PointSchemeRule::insert(array_map(fn (array $row) => [
            'point_scheme_id' => $schemeId,
            'format' => $row['format'] ?? 'se',
            'place_from' => $row['place_from'],
            'place_to' => $row['place_to'],
            'points' => $row['points'],
        ], $rows));
    }

    protected function insertDefaultSeRules(int $schemeId): void
    {
        $this->insertPointSchemeRules($schemeId, [
            ['place_from' => 1, 'place_to' => 1, 'points' => 12],
            ['place_from' => 2, 'place_to' => 2, 'points' => 10],
            ['place_from' => 3, 'place_to' => 3, 'points' => 8],
            ['place_from' => 4, 'place_to' => 4, 'points' => 6],
            ['place_from' => 5, 'place_to' => 8, 'points' => 4],
        ]);
    }
}
