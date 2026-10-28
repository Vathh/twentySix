<?php

namespace App\Domain\Activity\Definitions;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDefinition;
use App\Domain\Activity\ActivityType;

final class PlayerBadgesActivity implements ActivityDefinition
{
    public function type(): ActivityType
    {
        return ActivityType::PlayerBadges;
    }

    public function channels(): array
    {
        return [ActivityChannel::Friends];
    }

    public function subjectType(): string
    {
        return 'match';
    }

    public function replacesExisting(): bool
    {
        return true;
    }

    public function authoredByOrganization(): bool
    {
        return false;
    }

    public function kind(): array
    {
        return ['code' => 'badge', 'label' => 'Odznaka'];
    }

    public function sentence(array $payload): string
    {
        $actor = (string) ($payload['actorName'] ?? 'Gracz');
        $labels = [];
        foreach ($payload['badges'] ?? [] as $badge) {
            if (! is_array($badge)) {
                continue;
            }
            $title = trim((string) ($badge['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $level = trim((string) ($badge['levelLabel'] ?? ''));
            $labels[] = $level !== '' ? "{$title} — {$level}" : $title;
        }

        if (count($labels) <= 1) {
            return "{$actor} właśnie zdobył odznakę ".($labels[0] ?? 'nowy poziom').'.';
        }

        return "{$actor} właśnie zdobył odznaki: ".implode(', ', $labels).'.';
    }
}
