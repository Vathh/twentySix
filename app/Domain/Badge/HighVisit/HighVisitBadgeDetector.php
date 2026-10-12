<?php

namespace App\Domain\Badge\HighVisit;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\BadgeDetector;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\GameEligibility;

final class HighVisitBadgeDetector implements BadgeDetector
{
    public function detect(FinishedGame $game): array
    {
        if (! GameEligibility::allowsCheckoutBadges($game)) {
            return [];
        }

        $counts = [];
        foreach ($game->visits as $visit) {
            if ($visit['isVoided'] || $visit['bust']) {
                continue;
            }
            if (! GameEligibility::isRegistered($game, $visit['playerId'])) {
                continue;
            }
            $badgeKey = HighVisitCatalog::keyForScore($visit['score']);
            if ($badgeKey === null) {
                continue;
            }

            $key = $visit['playerId'].':'.$badgeKey;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $events = [];
        foreach ($counts as $key => $amount) {
            [$playerId, $badgeKey] = explode(':', $key, 2);
            $events[] = [
                'playerId' => (int) $playerId,
                'category' => BadgeCategory::HighVisit->value,
                'badgeKey' => $badgeKey,
                'amount' => $amount,
            ];
        }

        return $events;
    }
}
