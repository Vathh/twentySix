<?php

namespace App\Domain\Training;

/**
 * Punktacja 54-Dart Challenge. Kolejność etapów: Shanghai 10–15, Doubles, Shanghai 16–20.
 */
final class FiftyFourDartChallenge
{
    public const KEY = TrainingChallengeCatalog::FIFTY_FOUR_DART;

    /** @deprecated użyj KEY */
    public const MECHANIC = self::KEY;

    public const MAX_SCORE = 3190;

    public const SHANGHAI_SINGLE = 15;

    public const SHANGHAI_DOUBLE = 30;

    public const SHANGHAI_TRIPLE = 45;

    public const SHANGHAI_BONUS = 100;

    public const DOUBLE_HIT = 50;

    public const INNER_BULL = 100;

    public const VISIT_SIZE = 3;

    /**
     * @return list<array{id: string, label: string, type: string, targets: list<string>}>
     */
    public static function stages(): array
    {
        return [
            [
                'id' => 'shanghai_10_15',
                'label' => 'Shanghai 10–15',
                'type' => 'shanghai',
                'targets' => ['10', '11', '12', '13', '14', '15'],
            ],
            [
                'id' => 'doubles',
                'label' => 'Doubles',
                'type' => 'doubles',
                'targets' => [
                    'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'D9', 'D10',
                    'D11', 'D12', 'D13', 'D14', 'D15', 'D16', 'D17', 'D18', 'D19', 'D20',
                    'Bull',
                ],
            ],
            [
                'id' => 'shanghai_16_20',
                'label' => 'Shanghai 16–20',
                'type' => 'shanghai',
                'targets' => ['16', '17', '18', '19', '20'],
            ],
        ];
    }

    public static function totalDarts(): int
    {
        $total = 0;
        foreach (self::stages() as $stage) {
            $total += $stage['type'] === 'shanghai'
                ? count($stage['targets']) * self::VISIT_SIZE
                : count($stage['targets']);
        }

        return $total;
    }

    /**
     * @param  list<array{stageId: string, target: string, ring: string}>  $darts
     * @return array{
     *     score: int,
     *     stages: list<array{id: string, label: string, score: int}>,
     *     complete: bool
     * }
     */
    public static function score(array $darts): array
    {
        $byStage = [];
        foreach ($darts as $dart) {
            if (! is_array($dart)) {
                continue;
            }
            $stageId = (string) ($dart['stageId'] ?? '');
            if ($stageId === '') {
                continue;
            }
            $byStage[$stageId][] = $dart;
        }

        $stages = [];
        $total = 0;
        $expected = 0;
        foreach (self::stages() as $stage) {
            $stageDarts = $byStage[$stage['id']] ?? [];
            $stageScore = $stage['type'] === 'shanghai'
                ? self::scoreShanghai($stageDarts, count($stage['targets']))
                : self::scoreDoubles($stageDarts, count($stage['targets']));
            $expected += $stage['type'] === 'shanghai'
                ? count($stage['targets']) * self::VISIT_SIZE
                : count($stage['targets']);
            $stages[] = [
                'id' => $stage['id'],
                'label' => $stage['label'],
                'score' => $stageScore,
            ];
            $total += $stageScore;
        }

        return [
            'score' => $total,
            'stages' => $stages,
            'complete' => count($darts) >= $expected,
        ];
    }

    /**
     * @param  list<array{ring: string}>  $darts
     */
    private static function scoreShanghai(array $darts, int $sectorCount): int
    {
        $needed = $sectorCount * self::VISIT_SIZE;
        $score = 0;
        for ($visit = 0; $visit < $sectorCount; $visit++) {
            $rings = [];
            for ($offset = 0; $offset < self::VISIT_SIZE; $offset++) {
                $index = $visit * self::VISIT_SIZE + $offset;
                if ($index >= count($darts) || $index >= $needed) {
                    break 2;
                }
                $ring = (string) ($darts[$index]['ring'] ?? 'miss');
                $rings[] = $ring;
                $score += match ($ring) {
                    'single' => self::SHANGHAI_SINGLE,
                    'double' => self::SHANGHAI_DOUBLE,
                    'triple' => self::SHANGHAI_TRIPLE,
                    default => 0,
                };
            }
            if (self::hasShanghaiSet($rings)) {
                $score += self::SHANGHAI_BONUS;
            }
        }

        return $score;
    }

    /**
     * @param  list<string>  $rings
     */
    private static function hasShanghaiSet(array $rings): bool
    {
        return in_array('single', $rings, true)
            && in_array('double', $rings, true)
            && in_array('triple', $rings, true);
    }

    /**
     * @param  list<array{ring: string, target?: string}>  $darts
     */
    private static function scoreDoubles(array $darts, int $targetCount): int
    {
        $score = 0;
        $limit = min(count($darts), $targetCount);
        for ($i = 0; $i < $limit; $i++) {
            $ring = (string) ($darts[$i]['ring'] ?? 'miss');
            $target = (string) ($darts[$i]['target'] ?? '');
            if ($target === 'Bull') {
                if ($ring === 'inner') {
                    $score += self::INNER_BULL;
                }

                continue;
            }
            if ($ring === 'double') {
                $score += self::DOUBLE_HIT;
            }
        }

        return $score;
    }
}
