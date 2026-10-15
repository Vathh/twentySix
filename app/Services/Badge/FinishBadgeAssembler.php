<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Finish\FinishCatalog;
use App\Domain\Badge\Finish\FinishLevelPolicy;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Repositories\Player\PlayerGameHistoryRepository;
use App\Support\Badge\BadgeArtworkFrame;

class FinishBadgeAssembler
{
    public function __construct(
        private PlayerBadgeRepository $playerBadgeRepository,
        private PlayerGameHistoryRepository $playerGameHistoryRepository,
    ) {}

    /**
     * Finish 100+ — także przy zerze trafień, żeby pasek do żelaza był widoczny.
     *
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
        $badges = $this->playerBadgeRepository->forPlayerCategory($playerId, BadgeCategory::Finish->value);
        $hits = [];
        $lastAt = [];
        foreach ($badges as $badge) {
            $key = (string) $badge->badge_key;
            if (! in_array($key, FinishCatalog::KEYS, true)) {
                continue;
            }
            $hits[$key] = (int) $badge->times_earned;
            $earnedAt = $badge->last_earned_at;
            $lastAt[$key] = $earnedAt instanceof \DateTimeInterface
                ? $earnedAt->format(\DateTimeInterface::ATOM)
                : (is_string($earnedAt) ? $earnedAt : null);
        }

        $latest = $this->playerBadgeRepository->latestEventsByBadgeKey($playerId, BadgeCategory::Finish->value);
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
        foreach (FinishCatalog::KEYS as $key) {
            $times = $hits[$key] ?? 0;
            $levelName = FinishLevelPolicy::nameForHits($key, $times);
            $nextLevelName = FinishLevelPolicy::nextLevelName($key, $times);
            $progress = FinishLevelPolicy::progress($key, $times);
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
                'label' => FinishCatalog::label($key),
                'description' => FinishCatalog::description($key),
                'timesEarned' => $times,
                'level' => FinishLevelPolicy::levelForHits($key, $times),
                'levelName' => $levelName,
                'levelNumber' => FinishLevelPolicy::numberForName($levelName),
                'levelLabel' => FinishLevelPolicy::label($levelName),
                'nextThreshold' => FinishLevelPolicy::nextThreshold($key, $times),
                'nextLevelName' => $nextLevelName,
                'nextLevelLabel' => FinishLevelPolicy::label($nextLevelName),
                'progressPercent' => $progress['percent'],
                'progressLabel' => $progress['label'],
                'image' => $image = FinishCatalog::imagePath($key, $levelName),
                'artwork' => BadgeArtworkFrame::placement($image),
                'lastEarnedAt' => $lastAt[$key] ?? $eventCreatedAt,
                'lastGame' => $game,
            ];
        }

        return $items;
    }
}
