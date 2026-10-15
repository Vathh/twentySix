<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\HighVisit\HighVisitCatalog;
use App\Domain\Badge\HighVisit\HighVisitLevelPolicy;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Player\PlayerGameHistoryRepository;
use App\Support\Badge\BadgeArtworkFrame;

class HighVisitBadgeAssembler
{
    public function __construct(
        private PlayerBadgeRepository $playerBadgeRepository,
        private PlayerGameHistoryRepository $playerGameHistoryRepository,
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     timesEarned: int,
     *     level: int,
     *     levelName: string,
     *     levelNumber: int,
     *     levelLabel: string|null,
     *     nextThreshold: int|null,
     *     nextLevelName: string|null,
     *     nextLevelLabel: string|null,
     *     progressPercent: int,
     *     progressLabel: string,
     *     image: string,
     *     artwork: array{width: float, height: float, left: float, top: float},
     *     lastEarnedAt: string|null,
     *     lastGame: array{type: string, opponents: string, dateFormatted: string, tournamentName: string|null}|null
     * }>
     */
    public function itemsForPlayer(int $playerId): array
    {
        $badges = $this->playerBadgeRepository->forPlayerCategory($playerId, BadgeCategory::HighVisit->value);
        $hits = [];
        $lastAt = [];
        foreach ($badges as $badge) {
            $key = (string) $badge->badge_key;
            if (! in_array($key, HighVisitCatalog::KEYS, true)) {
                continue;
            }
            $hits[$key] = (int) $badge->times_earned;
            $earnedAt = $badge->last_earned_at;
            $lastAt[$key] = $earnedAt instanceof \DateTimeInterface
                ? $earnedAt->format(\DateTimeInterface::ATOM)
                : (is_string($earnedAt) ? $earnedAt : null);
        }

        $latest = $this->playerBadgeRepository->latestEventsByBadgeKey($playerId, BadgeCategory::HighVisit->value);
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
        foreach (HighVisitCatalog::KEYS as $key) {
            $times = $hits[$key] ?? 0;
            $levelName = HighVisitLevelPolicy::nameForHits($key, $times);
            $nextLevelName = HighVisitLevelPolicy::nextLevelName($key, $times);
            $progress = HighVisitLevelPolicy::progress($key, $times);
            $event = $latest[$key] ?? null;
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
                'key' => $key,
                'label' => HighVisitCatalog::label($key),
                'description' => HighVisitCatalog::description($key),
                'timesEarned' => $times,
                'level' => HighVisitLevelPolicy::levelForHits($key, $times),
                'levelName' => $levelName,
                'levelNumber' => HighVisitLevelPolicy::numberForName($levelName),
                'levelLabel' => HighVisitLevelPolicy::label($levelName),
                'nextThreshold' => HighVisitLevelPolicy::nextThreshold($key, $times),
                'nextLevelName' => $nextLevelName,
                'nextLevelLabel' => HighVisitLevelPolicy::label($nextLevelName),
                'progressPercent' => $progress['percent'],
                'progressLabel' => $progress['label'],
                'image' => $image = HighVisitCatalog::imagePath($key, $levelName),
                'artwork' => BadgeArtworkFrame::placement($image),
                'lastEarnedAt' => $lastAt[$key] ?? $eventCreatedAt,
                'lastGame' => $game,
            ];
        }

        return $items;
    }
}
