<?php

namespace App\Domain\Activity\Definitions;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDefinition;
use App\Domain\Activity\ActivityType;

final class TournamentAnnouncedActivity implements ActivityDefinition
{
    public function type(): ActivityType
    {
        return ActivityType::TournamentAnnounced;
    }

    public function channels(): array
    {
        return [ActivityChannel::Organization];
    }

    public function subjectType(): string
    {
        return 'tournament';
    }

    public function replacesExisting(): bool
    {
        return true;
    }

    public function authoredByOrganization(): bool
    {
        return true;
    }

    public function kind(): array
    {
        return ['code' => 'announce', 'label' => 'Turniej'];
    }

    public function sentence(array $payload): string
    {
        $tournament = (string) ($payload['tournamentName'] ?? 'turniej');
        $organization = trim((string) ($payload['organizationName'] ?? ''));

        if ($organization === '') {
            return "Nowy turniej {$tournament}.";
        }

        return "Nowy turniej {$tournament} w {$organization}.";
    }
}
