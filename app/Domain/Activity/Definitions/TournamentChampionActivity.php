<?php

namespace App\Domain\Activity\Definitions;

use App\Domain\Activity\ActivityChannel;
use App\Domain\Activity\ActivityDefinition;
use App\Domain\Activity\ActivityType;

final class TournamentChampionActivity implements ActivityDefinition
{
    public function type(): ActivityType
    {
        return ActivityType::TournamentChampion;
    }

    public function channels(): array
    {
        return [ActivityChannel::Friends, ActivityChannel::Organization];
    }

    public function subjectType(): string
    {
        return 'tournament';
    }

    public function replacesExisting(): bool
    {
        return true;
    }

    public function kind(): array
    {
        return ['code' => 'win', 'label' => 'Wygrana'];
    }

    public function sentence(array $payload): string
    {
        $actor = (string) ($payload['actorName'] ?? 'Gracz');
        $tournament = (string) ($payload['tournamentName'] ?? 'turniej');
        $organization = trim((string) ($payload['organizationName'] ?? ''));

        if ($organization === '') {
            return "{$actor} właśnie wygrał turniej {$tournament}.";
        }

        return "{$actor} właśnie wygrał turniej {$tournament} w {$organization}.";
    }
}
