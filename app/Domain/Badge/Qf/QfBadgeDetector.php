<?php

namespace App\Domain\Badge\Qf;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\BadgeDetector;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\GameEligibility;

final class QfBadgeDetector implements BadgeDetector
{
    public function detect(FinishedGame $game): array
    {
        if (! GameEligibility::allowsCheckoutBadges($game)) {
            return [];
        }

        $counts = [];
        foreach ($game->legs as $leg) {
            if (! GameEligibility::isRegistered($game, $leg['playerId'])) {
                continue;
            }
            $badgeKey = QfCatalog::keyForDarts($leg['dartsThrown']);
            if ($badgeKey === null) {
                continue;
            }

            $key = $leg['playerId'].':'.$badgeKey;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $events = [];
        foreach ($counts as $key => $amount) {
            [$playerId, $badgeKey] = explode(':', $key, 2);
            $events[] = [
                'playerId' => (int) $playerId,
                'category' => BadgeCategory::Qf->value,
                'badgeKey' => $badgeKey,
                'amount' => $amount,
            ];
        }

        return $events;
    }
}
