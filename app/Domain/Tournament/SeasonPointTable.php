<?php

namespace App\Domain\Tournament;

use App\Enums\PointSchemeFormat;

/**
 * Tabele punktacji sezonowej — źródło liczb zgodne z docs/design_point_schemes.md.
 * Seeder tylko materializuje te stałe w DB.
 *
 * Przyszłe pasmo 129–256: gdy limit stawki wzrośnie; na razie N max 128.
 */
final class SeasonPointTable
{
    /**
     * @return list<array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     rules: list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::band('od 4 do 8 osób', 4, 8, [
                [1, 1, 13],
                [2, 2, 10],
                [3, 3, 8],
                [4, 4, 6],
                [5, 8, 5],
            ], [
                [1, 1, 13],
                [2, 2, 10],
                [3, 3, 8],
                [4, 4, 6],
                [5, 6, 5],
                [7, 8, 3],
            ]),
            self::band('od 9 do 16 osób', 9, 16, [
                [1, 1, 20],
                [2, 2, 16],
                [3, 3, 13],
                [4, 4, 10],
                [5, 8, 7],
                [9, 16, 5],
            ], [
                [1, 1, 20],
                [2, 2, 16],
                [3, 3, 13],
                [4, 4, 10],
                [5, 6, 8],
                [7, 8, 7],
                [9, 12, 6],
                [13, 16, 4],
            ]),
            self::band('od 17 do 32 osób', 17, 32, [
                [1, 1, 26],
                [2, 2, 22],
                [3, 3, 18],
                [4, 4, 14],
                [5, 8, 10],
                [9, 16, 7],
                [17, 32, 4],
            ], [
                [1, 1, 26],
                [2, 2, 22],
                [3, 3, 18],
                [4, 4, 14],
                [5, 6, 12],
                [7, 8, 10],
                [9, 12, 8],
                [13, 16, 7],
                [17, 24, 5],
                [25, 32, 3],
            ]),
            self::band('od 33 do 64 osób', 33, 64, [
                [1, 1, 39],
                [2, 2, 33],
                [3, 3, 28],
                [4, 4, 23],
                [5, 8, 18],
                [9, 16, 13],
                [17, 32, 8],
                [33, 64, 4],
            ], [
                [1, 1, 39],
                [2, 2, 33],
                [3, 3, 28],
                [4, 4, 23],
                [5, 6, 20],
                [7, 8, 18],
                [9, 12, 15],
                [13, 16, 13],
                [17, 24, 10],
                [25, 32, 8],
                [33, 48, 5],
                [49, 64, 3],
            ]),
            self::band('od 65 do 128 osób', 65, 128, [
                [1, 1, 52],
                [2, 2, 45],
                [3, 3, 38],
                [4, 4, 32],
                [5, 8, 24],
                [9, 16, 17],
                [17, 32, 11],
                [33, 64, 6],
                [65, 128, 3],
            ], [
                [1, 1, 52],
                [2, 2, 45],
                [3, 3, 38],
                [4, 4, 32],
                [5, 6, 28],
                [7, 8, 24],
                [9, 12, 20],
                [13, 16, 17],
                [17, 24, 14],
                [25, 32, 11],
                [33, 48, 8],
                [49, 64, 6],
                [65, 96, 4],
                [97, 128, 2],
            ]),
        ];
    }

    public static function pointsFor(int $minPlayers, PointSchemeFormat $format, int $place): ?int
    {
        foreach (self::definitions() as $band) {
            if ($band['min_players'] !== $minPlayers) {
                continue;
            }

            foreach ($band['rules'] as $rule) {
                if ($rule['format'] === $format
                    && $place >= $rule['place_from']
                    && $place <= $rule['place_to']) {
                    return $rule['points'];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $se
     * @param  list<array{0: int, 1: int, 2: int}>  $de
     * @return array{
     *     name: string,
     *     min_players: int,
     *     max_players: int,
     *     rules: list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     * }
     */
    private static function band(string $name, int $min, int $max, array $se, array $de): array
    {
        return [
            'name' => $name,
            'min_players' => $min,
            'max_players' => $max,
            'rules' => [
                ...self::ranges(PointSchemeFormat::Se, $se),
                ...self::ranges(PointSchemeFormat::De, $de),
            ],
        ];
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $ranges
     * @return list<array{format: PointSchemeFormat, place_from: int, place_to: int, points: int}>
     */
    private static function ranges(PointSchemeFormat $format, array $ranges): array
    {
        return array_map(
            fn (array $range) => [
                'format' => $format,
                'place_from' => $range[0],
                'place_to' => $range[1],
                'points' => $range[2],
            ],
            $ranges,
        );
    }
}
