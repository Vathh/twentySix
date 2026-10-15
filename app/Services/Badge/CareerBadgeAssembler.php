<?php

namespace App\Services\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\Career\CareerCatalog;
use App\Domain\Badge\Career\CareerLevelPolicy;
use App\Repositories\Badge\CareerBadgeReadRepository;
use App\Repositories\Badge\PlayerBadgeRepository;
use App\Support\Badge\BadgeArtworkFrame;

class CareerBadgeAssembler
{
    public function __construct(
        private PlayerBadgeRepository $playerBadgeRepository,
        private CareerBadgeReadRepository $careerBadgeReadRepository,
    ) {}

    /**
     * Cztery odznaki kariery, także przy zerze — pasek do żelaza zostaje widoczny.
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
        $badges = $this->playerBadgeRepository->forPlayerCategory($playerId, BadgeCategory::Career->value);
        $hits = [];
        $lastAt = [];
        foreach ($badges as $badge) {
            $key = (string) $badge->badge_key;
            if (! in_array($key, CareerCatalog::KEYS, true)) {
                continue;
            }
            $hits[$key] = (int) $badge->times_earned;
            $earnedAt = $badge->last_earned_at;
            $lastAt[$key] = $earnedAt instanceof \DateTimeInterface
                ? $earnedAt->format(\DateTimeInterface::ATOM)
                : (is_string($earnedAt) ? $earnedAt : null);
        }

        $latest = $this->playerBadgeRepository->latestEventsByBadgeKey($playerId, BadgeCategory::Career->value);
        $tournamentIds = [];
        foreach ($latest as $event) {
            if ($event['source_kind'] === CareerCatalog::SOURCE_KIND) {
                $tournamentIds[] = $event['source_id'];
            }
        }
        $names = $this->careerBadgeReadRepository->tournamentNames($tournamentIds);

        $items = [];
        foreach (CareerCatalog::KEYS as $key) {
            $times = $hits[$key] ?? 0;
            $levelName = CareerLevelPolicy::nameForHits($key, $times);
            $nextLevelName = CareerLevelPolicy::nextLevelName($key, $times);
            $progress = CareerLevelPolicy::progress($key, $times);
            $event = $latest[$key] ?? null;
            $game = null;
            $eventCreatedAt = null;
            if (is_array($event) && $event['source_kind'] === CareerCatalog::SOURCE_KIND) {
                $eventCreatedAt = $event['created_at'] ?? null;
                $game = [
                    'type' => 'tournament',
                    'opponents' => '',
                    'dateFormatted' => $eventCreatedAt !== null
                        ? date('d.m.Y', strtotime($eventCreatedAt))
                        : '',
                    'tournamentName' => $names[$event['source_id']] ?? null,
                ];
            }

            $items[] = [
                'key' => $key,
                'label' => CareerCatalog::label($key),
                'description' => CareerCatalog::description($key),
                'timesEarned' => $times,
                'level' => CareerLevelPolicy::levelForHits($key, $times),
                'levelName' => $levelName,
                'levelNumber' => CareerLevelPolicy::numberForName($levelName),
                'levelLabel' => CareerLevelPolicy::label($levelName),
                'nextThreshold' => CareerLevelPolicy::nextThreshold($key, $times),
                'nextLevelName' => $nextLevelName,
                'nextLevelLabel' => CareerLevelPolicy::label($nextLevelName),
                'progressPercent' => $progress['percent'],
                'progressLabel' => $progress['label'],
                'image' => $image = CareerCatalog::imagePath($key, $levelName),
                'artwork' => BadgeArtworkFrame::placement($image),
                'lastEarnedAt' => $lastAt[$key] ?? $eventCreatedAt,
                'lastGame' => $game,
            ];
        }

        return $items;
    }
}
