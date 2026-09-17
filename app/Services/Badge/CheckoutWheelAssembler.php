<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Checkout\CheckoutCatalog;
use App\Domain\Badge\Checkout\CheckoutLevelPolicy;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Player\PlayerGameHistoryRepository;

class CheckoutWheelAssembler
{
    public function __construct(
        private PlayerBadgeRepository $playerBadgeRepository,
        private PlayerGameHistoryRepository $playerGameHistoryRepository,
    ) {}

    /**
     * Mapa checkout → times_earned dla inline SVG / JS.
     *
     * @return array<int, int>
     */
    public function hitsForPlayer(int $playerId): array
    {
        $hits = [];
        foreach ($this->itemsForPlayer($playerId) as $item) {
            $times = (int) $item['timesEarned'];
            if ($times > 0) {
                $hits[(int) $item['key']] = $times;
            }
        }

        return $hits;
    }

    /**
     * @return list<array{
     *     key: string,
     *     timesEarned: int,
     *     level: int,
     *     levelName: string,
     *     lastEarnedAt: string|null,
     *     lastGame: array{type: string, opponents: string, dateFormatted: string, tournamentName: string|null}|null
     * }>
     */
    public function itemsForPlayer(int $playerId): array
    {
        $badges = $this->playerBadgeRepository->forPlayerCategory($playerId, BadgeCategory::Checkout->value);
        $hits = [];
        $lastAt = [];
        foreach ($badges as $badge) {
            $key = (int) $badge->badge_key;
            if ($key <= 0) {
                continue;
            }
            $hits[$key] = (int) $badge->times_earned;
            $earnedAt = $badge->last_earned_at;
            $lastAt[$key] = $earnedAt instanceof \DateTimeInterface
                ? $earnedAt->format(\DateTimeInterface::ATOM)
                : (is_string($earnedAt) ? $earnedAt : null);
        }

        $latest = $this->playerBadgeRepository->latestEventsByBadgeKey($playerId, BadgeCategory::Checkout->value);
        $gameRefs = [];
        $seen = [];
        foreach ($latest as $event) {
            $refKey = $event['source_kind'].':'.$event['source_id'];
            if (isset($seen[$refKey])) {
                continue;
            }
            $seen[$refKey] = true;
            $gameRefs[] = $event;
        }
        $games = $gameRefs === []
            ? []
            : $this->playerGameHistoryRepository->summariesForBadgeSources($playerId, $gameRefs);

        $items = [];
        foreach (CheckoutCatalog::all() as $key) {
            $times = $hits[$key] ?? 0;
            $event = $latest[(string) $key] ?? null;
            $game = null;
            $eventCreatedAt = null;
            if (is_array($event)) {
                $eventCreatedAt = $event['created_at'] ?? null;
                $summary = $games[$event['source_kind'].':'.$event['source_id']] ?? null;
                if (is_array($summary)) {
                    $game = [
                        'type' => (string) $summary['type'],
                        'opponents' => (string) $summary['opponents'],
                        'dateFormatted' => (string) $summary['date_formatted'],
                        'tournamentName' => $summary['tournament_name'] !== null ? (string) $summary['tournament_name'] : null,
                    ];
                }
            }

            $items[] = [
                'key' => (string) $key,
                'timesEarned' => $times,
                'level' => CheckoutLevelPolicy::levelForHits($times),
                'levelName' => CheckoutLevelPolicy::nameForHits($times),
                'lastEarnedAt' => $lastAt[$key] ?? $eventCreatedAt,
                'lastGame' => $game,
            ];
        }

        return $items;
    }
}
