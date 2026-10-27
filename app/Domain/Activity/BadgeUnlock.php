<?php

namespace App\Domain\Activity;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Career\CareerCatalog;
use App\Domain\Badge\Career\CareerLevelPolicy;
use App\Domain\Badge\Checkout\CheckoutLevelPolicy;
use App\Domain\Badge\Finish\FinishCatalog;
use App\Domain\Badge\Finish\FinishLevelPolicy;
use App\Domain\Badge\HighVisit\HighVisitCatalog;
use App\Domain\Badge\HighVisit\HighVisitLevelPolicy;
use App\Domain\Badge\Qf\QfCatalog;
use App\Domain\Badge\Qf\QfLevelPolicy;

/**
 * Wyższy poziom odznaki. Skok o kilka progów w jednym meczu zostawia tylko osiągnięty stopień.
 */
final class BadgeUnlock
{
    /** @var list<string> */
    private const COMMON = ['locked', 'iron', 'bronze', 'gold', 'platinum', 'diamond'];

    /** @var list<string> */
    private const CHECKOUT = ['locked', 'iron', 'bronze', 'gold', 'bright', 'apex'];

    /** @var array<string, string> */
    private const LEVEL_LABELS = [
        'iron' => 'Żelazo',
        'bronze' => 'Brąz',
        'gold' => 'Złoto',
        'platinum' => 'Platyna',
        'diamond' => 'Diament',
        'bright' => 'Jasny',
        'apex' => 'Apex',
    ];

    /**
     * @param  list<array{category: string, key: string, before: int, after: int}>  $steps
     * @return list<array{category: string, key: string, levelName: string, levelLabel: string, title: string}>
     */
    public static function collect(array $steps): array
    {
        $byBadge = [];
        foreach ($steps as $step) {
            $unlock = self::fromProgress($step['category'], $step['key'], $step['before'], $step['after']);
            if ($unlock === null) {
                continue;
            }
            $byBadge[$step['category'].'|'.$step['key']] = $unlock;
        }

        $items = array_values($byBadge);
        usort($items, fn (array $left, array $right): int => [$left['category'], $left['key']] <=> [$right['category'], $right['key']]);

        return $items;
    }

    /**
     * @return array{category: string, key: string, levelName: string, levelLabel: string, title: string}|null
     */
    public static function fromProgress(string $category, string $key, int $before, int $after): ?array
    {
        $beforeName = self::name($category, $key, $before);
        $afterName = self::name($category, $key, $after);
        if (self::rank($category, $afterName) <= self::rank($category, $beforeName)) {
            return null;
        }

        return [
            'category' => $category,
            'key' => $key,
            'levelName' => $afterName,
            'levelLabel' => self::LEVEL_LABELS[$afterName] ?? $afterName,
            'title' => self::title($category, $key),
        ];
    }

    public static function rank(string $category, string $levelName): int
    {
        $ladder = $category === BadgeCategory::Checkout->value ? self::CHECKOUT : self::COMMON;
        $index = array_search($levelName, $ladder, true);

        return $index === false ? 0 : $index;
    }

    private static function name(string $category, string $key, int $hits): string
    {
        return match ($category) {
            BadgeCategory::Checkout->value => CheckoutLevelPolicy::nameForHits($hits),
            BadgeCategory::HighVisit->value => isset(HighVisitLevelPolicy::THRESHOLDS[$key])
                ? HighVisitLevelPolicy::nameForHits($key, $hits)
                : 'locked',
            BadgeCategory::Qf->value => QfLevelPolicy::nameForHits($key, $hits),
            BadgeCategory::Finish->value => isset(FinishLevelPolicy::THRESHOLDS[$key])
                ? FinishLevelPolicy::nameForHits($key, $hits)
                : 'locked',
            BadgeCategory::Career->value => isset(CareerLevelPolicy::THRESHOLDS[$key])
                ? CareerLevelPolicy::nameForHits($key, $hits)
                : 'locked',
            default => 'locked',
        };
    }

    private static function title(string $category, string $key): string
    {
        return match ($category) {
            BadgeCategory::HighVisit->value => HighVisitCatalog::label($key),
            BadgeCategory::Qf->value => 'QF '.QfCatalog::label($key),
            BadgeCategory::Finish->value => 'Finish '.FinishCatalog::label($key),
            BadgeCategory::Checkout->value => 'Checkout '.$key,
            BadgeCategory::Career->value => CareerCatalog::label($key),
            default => $key,
        };
    }
}
